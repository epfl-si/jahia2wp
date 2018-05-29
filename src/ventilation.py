"""All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018 """
import sys
import csv
import logging
from pprint import pprint
from time import time as tt
from urllib.parse import urlparse
import subprocess
import os
import re
import json
import glob
import shutil
from settings import JAHIA2WP_VENT_TMP
from utils import Utils
from wordpress import WPSite, WPConfig
import yaml

"""
    It takes the mapping rules in a CSV, with 2 columns each: source => destination,
    where both are URLs in WP instances. The first row of the CSV are treated as
    headers.

    It first validates the format of the CSV, where source can refer to a whole site,
    a path or a leaf (page). It can fix automatically the CSV,
    by removing or adding trailing slashes and peforming other checks. If the CSV is
    correct, the process will continue otherwise it will stop.
    The CSV will also be  sorted by rule specificity from specific to generic. The CSV
    is then split by site in an effort to treat them in parallel.

    For each site, all the post URLs are obtained from its WP instance in a python
    structure using WP-CLI.

    The URLs are then matched to the rules extracted for the site. The first match is
    taken and if no match, an alert is raised.

    Once an URL has been matched, its content (including pages and subpages if it's a
    site or path) are ready to be moved into the new location.

    The resulting post(s) to be moved are inserted by hierarchy to make sure the parent
    content is present before inserting sub-posts.

    When trying to run it in parallel, consider that the bottlneck will be the writes at
    the destination, since n sites will be migrated to a smaller number k destinations
    (likely 2 main: www.epfl.ch and inside.epfl.ch).
 """


class Ventilation:
    local_env = 'https://jahia2wp-httpd'
    wp_env = None
    csv_file = None
    strict_mode = True
    root_wp_dest = None
    context = "intra"
    htaccess = True
    rulesets = {}
    files = {}
    medias = {}
    widgets = {}
    site_paths = {}
    dest_sites = {}

    def __init__(self, wp_env, csv_file, greedy=False, root_wp_dest=None, htaccess=False,
                 context="intra", dry_run=False):
        self.wp_env = wp_env
        self.csv_file = csv_file
        self.strict_mode = not greedy
        self.root_wp_dest = root_wp_dest
        if context:
            self.context = context
        self.htaccess = htaccess
        self.dry_run = dry_run
        _rulesets = self.rule_parsing(self.csv_file)
        if _rulesets:
            self.rulesets = _rulesets
        else:
            logging.error('Could not load the rulesets from CSV file...')
        self.dest_sites = self.inventory(self.root_wp_dest)

    def inventory(self, root_wp_dest, use_default_inventory=False):
        """
        Obtain the list of the WP instances at the destination root folders (e.g. /srv/hmuriel/httpd/epfl).
        Ideally it's just one path to the root of www.epfl.ch (e.g. /srv/prod/www.epfl.ch) or more if the
        there are different trees.

        This method does a quick search to find all wp-config under the root path and then validate if it's
        a valid destination using a WPSite instance.

        There is also the slow version that scans for valid installs using the WPConfig.inventory() method which
        looks at every possible folder (inefficient).

        :param root_wp_dest: A list of absolute paths separated by colons (e.g. /srv/hmuriel/epfl,/srv/hmuriel/inside)
        :param use_default_inventory: Use the slow (default) or fast scanning method. By default is false (fast).

        return: A dictionary mapping site URL => site abs path.
        """

        dest_sites = {}
        logging.info('Parsing target WP instances (inventory) at {}'.format(root_wp_dest))
        if not root_wp_dest:
            logging.error("No target location to scan for WP instances")
        # Split the root to get the target sites (e.g. ["/srv/hmuriel/epfl", "/srv/hmuriel/inside"])
        for path in root_wp_dest.split(','):
            # The path must be absolute
            if not os.path.isabs(path):
                logging.warn(
                    'The target site path is not absolute: {}. skipping...'.format(path))
            else:
                # Using inventory() method == SLOW.... test: 15s
                if use_default_inventory:
                    for site in WPConfig.inventory(path):
                        if site.valid != 'ok':
                            logging.warn(
                                'Target site not valid: {}, skipping...'.format(site.path))
                        else:
                            dest_sites[site.url] = site.path
                else:
                    # Scan for valid instances in the target path (FAST method),
                    # test: 0.3s, test with wpcli check: 5.5s
                    # Find wp-config.php in the paths
                    dirs = Utils.run_command(
                        'find "' + path + '" -name "wp-config.php" -exec dirname "{}" \;')
                    if not dirs:
                        logging.error('Cannot find WP instances (wp-config.php) at {}'.format(path))
                    else:
                        dirs = dirs.split('\n')
                        # Create a WP Site per matching dir and get its URL
                        # Doing it this way since WP site has some checks for the path and URL building.
                        for path in dirs:
                            wp_site = WPSite.from_path(path)
                            if wp_site:
                                # wp_config = WPConfig(wp_site)
                                # if wp_config.is_config_valid:
                                dest_sites[wp_site.url] = path

        # Return the site_url => site_path mapping
        return dest_sites

    def _check_sites(self, sites):
        """
        Iterate over the list of sites and check if they are valid WP installs individually.
        """
        site_errs = []
        for site in sites:
            if not self._isvalid_site(site):
                cmd = 'Site {} is not installed or wp_config is invalid'
                logging.warning(cmd.format(site))
                site_errs += [site]

        return site_errs

    def _isvalid_site(self, site):
        """
        Check if the given site URL is a valid installation under /srv/WP_ENV/
        """
        wp_conf = WPConfig(WPSite(self.wp_env, site))

        if wp_conf.is_installed and wp_conf.is_config_valid:
            return wp_conf

        return None

    def rule_parsing(self, csv_file):
        """
        This function reads and loads the rules in a tree of site => rules like:

        {'dcsl.epfl.ch': [('https://dcsl.epfl.ch/p1', 'https://www.epfl.ch/path/p1'),
                        ('https://dcsl.epfl.ch/p1', 'https://www.epfl.ch/path/p1')],
         'vpi.epfl.ch': [('https://vpi.epfl.ch/about', 'https://www.epfl.ch/innovation/about')]
        }

        precondition: The CSV MUST be passed to the csv validator before. No other syntactic or
        semantic check in this function.

        :param csv_file: CSV with the URL migration rules
        """
        rules = {}
        if not os.path.isfile(self.csv_file):
            raise Exception('CSV file does not exist!')

        rows = Utils.csv_filepath_to_dict(self.csv_file)
        if not rows:
            raise Exception('Empty CSV file!')

        for idx, row in enumerate(rows):
            source = row['source']
            # Split the path and take the first arg as site
            # Consider special case of http(s):// and local sites
            # ATTENTION: At this stage, a site = domain name. Relative paths are not considered as sites.
            # this is true at least during the 'consolidation' phase wp => wp
            site_name = source.split('//').pop().split('/').pop(0)
            # There can be 3 type of rules identified
            # 1. Root path = sitename = full site
            # 2. Partial Path = Intermediate path with children
            # 3. Full path = a leaf / page with no children.
            # It's expected to find * in the rules but its absence has the same
            # meaning (i.e. apply the rule to all the sub-content under the path
            # Sytactically, only 2 cases will be detected: root and non-root path.

            # DO NOT remove the trailing * anymore, necessary to keep track in strict mode.
            source = source.strip()
            _source = source.strip('*')
            if _source != '':
                if 'http://' not in _source and 'https://' not in _source:
                    # Local development case, append the host
                    site = self.local_env + '/' + site_name
                    source = self.local_env + '/' + source
                else:
                    site = '{}//{}'.format(_source.split('//').pop(0), site_name)

            dest = row['destination'].strip()
            if dest != '':
                # IMPORTANT: Add trailing slash, specially since now the source gets translated
                # into the new intermediate WP URL that always has a trailing slash.
                dest = dest.strip('/') + '/'
                if 'http://' not in dest and 'https://' not in dest:
                    # Local development case, append the host
                    dest = self.local_env + '/' + dest

            # Start with an empty ruleset
            if site not in rules:
                rules[site] = []
            # Append the URL to the site's list
            rules[site].append((source, dest))

        return rules

    def dump_csv(self):
        """
        Iterate over all the sites to map and dump a CSV with the pages and
        another one for the media / attachments. This will *greatly simplify* the
        reinsertion.

        :param site_paths: Keep a copy of the installation paths to use WP cli later
        """

        # Create a copy of the keys to allow changes on the keyset
        for site in list(self.rulesets.keys()):
            logging.info('Treating site {}'.format(site))
            # By security, check if the site is a valid install, in case _check_sites was skipped.
            wp_conf = self._isvalid_site(site)
            if not wp_conf:
                cmd = 'Site {} is not installed or wp_config is invalid, _check_sites was not called?'
                logging.error(cmd.format(site))
                raise Exception('Invalid WP site! Did you call _check_sites?')

            # IMPORTANT: Increase the 'field_size_limit' to allow dumping certain sites like vpi.epfl.ch
            csv.field_size_limit(sys.maxsize)
            # Dump the site content in plain CSV format.
            logging.info("Dumping CSV for site {}".format(site))
            # All the fields to retrieve from the wp_post table
            fields = 'ID,post_title,post_name,post_parent,guid,url,post_status,post_content'
            # Only pages, all without paging. Sort them by post_parent ascendantly for ease of
            # reinsertion to insert first parent pages and avoiding parentless children.
            params = '--post_type=page --nopaging --order=asc --orderby=ID --fields={} --format=json'
            cmd = 'wp post list ' + params + ' --path={} > {}'
            csv_f = JAHIA2WP_VENT_TMP + '/j2wp_' + site.split('/').pop() + '.csv'
            self.files[site] = csv_f
            cmd = cmd.format(fields, wp_conf.wp_site.path, csv_f)
            logging.debug(cmd)
            Utils.run_command(cmd, 'utf8')

            # Check if it's a non empty CSV
            if os.path.getsize(csv_f) == 0:
                raise Exception('Empty CSV exported! Cannot continue')

            # FIX: Convert the JSON to *proper* CSV with python
            # This is necessary since WP-CLI can produce a non-standard CSV (e.g. not escaping colons)
            pages = json.load(open(csv_f, 'r', encoding='utf8'))
            if not pages:
                logging.error('No pages for site {}, removing it from rulesets..'.format(site))
                del self.rulesets[site]
                continue
            # Convert the GUIDs to relative URLs to avoid rule replacement by URL
            with open(csv_f, 'w', encoding='utf8') as f:
                site_url = urlparse(site)
                host = site_url._replace(path='').geturl()
                """IMPORTANT: The GUID column has to be in the middle or be the last one. This assumption is
                reused in the guid replacement col in text mode. In text mode, there is no notion of CSV columns
                and the delimiters have to be unambiguous (e.g. ,guid, => middle col - OK; ,guid => last col - OK;
                guid, => first col - NOT OK). Concretely, the first column poses a problem since it ends in partial
                matches and therefore inconsistent replacements."""
                header_cols = list(pages[0].keys())
                header_cols.remove('guid')
                header_cols.append('guid')
                writer = csv.DictWriter(f, fieldnames=header_cols)
                writer.writeheader()
                # FIX: scan if the content has relative URLs not starting with a slash /
                # in other words, links that don't point to GUIDs (i.e. /site_name/post_name but only post_name)
                regex = re.compile(r'href="([^\/]+)"')
                site_path = site_url.path
                for p in pages:
                    # Make it into a rel URL
                    p['guid'] = p['guid'].replace(host, '')
                    # Fix the rel links without domain / path info.
                    p['post_content'] = regex.sub(r'href="{}/\g<1>/"'.format(site_path), p['post_content'])
                    writer.writerow(p)
            # Backup file
            shutil.copyfile(csv_f, csv_f + '.bak')

            # Dump all the widgets for the site in json format
            sidebars_content = {}
            wid = JAHIA2WP_VENT_TMP + '/j2wp_' + site.split('/').pop() + '_widgets.yaml'
            # List the registered sidebars in the source site
            cmd = 'wp sidebar list --format=ids --path={}'.format(wp_conf.wp_site.path)
            sidebars = Utils.run_command(cmd, 'utf8').split(' ')
            for side_id in sidebars:
                # Get the sidebar entries if any
                cmd = 'wp widget list {} --format=json --path={}'.format(side_id, wp_conf.wp_site.path)
                side_entries = json.loads(Utils.run_command(cmd, 'utf8'))
                sidebars_content[side_id] = side_entries
            # Save the sidebars in YAML format to have *CLEAN URLs* (i.e. not backslash escaped) for URL replacements
            with open(wid, 'w', encoding="utf8") as f:
                f.write(yaml.dump(sidebars_content))
            self.widgets[site] = wid

            # Dump media / attachments
            fields = 'ID,post_title,post_name,post_parent,post_status,guid'
            params = '--post_type=attachment --nopaging --order=asc --fields={} --format=csv'
            cmd = 'wp post list ' + params + ' --path={} > {}'
            csv_m = JAHIA2WP_VENT_TMP + '/j2wp_' + site.split('/').pop() + '_media.csv'
            self.medias[site] = csv_m
            cmd = cmd.format(fields, wp_conf.wp_site.path, csv_m)
            logging.debug(cmd)
            Utils.run_command(cmd, 'utf8')
            # Add a column file_path to indicate where the media is located physically ####
            wp_medias = Utils.csv_filepath_to_dict(csv_m)
            # Write back the CSV file
            with open(csv_m, 'w', encoding='utf8') as f:
                fields = list(wp_medias[0].keys()) + ['file_path']
                writer = csv.DictWriter(f, fieldnames=fields)
                writer.writeheader()
                for m in wp_medias:
                    m['file_path'] = m['guid'].replace(site, wp_conf.wp_site.path)
                    writer.writerow(m)
            # Backup file
            shutil.copyfile(csv_m, csv_m + '.bak')

            # Add an entry to the site paths dict
            self.site_paths[site] = wp_conf.wp_site.path

        return True

    def rule_expansion(self):
        """
        The rule expansion has 2 parts.

        1) The first one is to lookup for the full URL at the WP intermediate
        site, all URLs have to be HTTPS. The mgmt container has to look (cURL) on the 8443 port. This process
        is performed twice if necessary till the Location header does not contain the 8443 port. This is due
        to the fact that the JAHIA URl (e.g. sac/echange-PDM) is redirected to a GUID (short URL) on the
        intermediate WP, which has to be decoded into a full WP URL (e.g. sac/index-html/echange-pdm)

        Full example:
        JAHIA URL:          https://vpi.epfl.ch/centres
        cURL query:         https://vpi.epfl.ch:8443/centres
        WP REDIR (GUID):    https://vpi.epfl.ch:8443/ip/
        WP URL:             https://vpi.epfl.ch/fr/index-fr-html/ip/

        2) In the case of multilang sites, the URL has to be expanded (retrieved) in all languages. This is
        as simply as looking into the CSV file, finding the WP URL (see above) and hence the group of pages
        in all languages.
        """

        ############
        # IMPORTANT: Translate the source URL using the intermediate WP instance.
        ############

        # Use port 8443 for local_env only (wp-mgmt does not have 443=>8443 redirection for httpd cont.)
        for site in self.rulesets.keys():
            # IMPORTANT: Before expanding any rules, restore the .htaccess to remove ventilation redirs.
            # Restore the .htaccess (always)
            with open(self.site_paths[site] + '/.htaccess', 'r+', encoding='utf8') as f:
                lines = f.readlines()
                f.seek(0, 0)
                f.truncate()
                begin = [i for i, l in enumerate(lines) if 'BEGIN ventilation-redirs' in l] or [len(lines)]
                end = [i for i, l in enumerate(lines) if 'END ventilation-redirs' in l] or [len(lines)]
                for i, l in enumerate(lines):
                    if i < begin[0] or i > end[0]:
                        f.write(l)
            # Iterate over the individual rules for the site
            ext_ruleset = []
            # Expand the rules to cover additional languages for multilang websites.
            # By default, there is only 1 rule (in the csv) per URL independently of
            # the number of langs.
            lngs = Utils.run_command('wp pll languages --path=' + self.site_paths[site])
            lngs = [l[:2] for l in lngs.split("\n")]
            if len(lngs) > 1:
                logging.info('Multilang site {} for {}, decoupling rules...'.format(lngs, site))

            for (src, dst) in self.rulesets[site]:
                # If the source is empty (verb: create empty page), do not expand.
                if src == '':
                    ext_ruleset.append((src, dst))
                    continue

                orig_src = src
                src_url = urlparse(src)
                src_url = src_url._replace(netloc=src_url.netloc + ':8443').geturl()
                # GET only the HEADERS *of course* in silent mode and ignoring cert validity
                # WARNING:::: This first curl call will only get the .htaccess redirection (i.e. page GUID)
                # The second call (redir) will translate the GUID into the actual URL that doesn't have a port
                # info, therefore curl has to stop at this level to avoid port errors.
                out = Utils.run_command('curl -I -s -k {}'.format(src_url))
                # Parse the Location header if present.
                loc = [l.split('Location: ').pop().strip() for l in out.split('\n') if 'Location:' in l]
                if not loc:
                    logging.warning('cURL fail for URL location in WP instance for {}, removing rule'.format(src))
                    continue
                else:
                    src = loc.pop()
                    # Continue only if the location has a 8443 port.
                    # Special case: The root URL does only need one cURL.
                    if ':8443' in src:
                        out = Utils.run_command('curl -I -s -k {}'.format(src))
                        # Parse the Location header if present.
                        loc = [l.split('Location: ').pop().strip() for l in out.split('\n') if 'Location:' in l]
                        if not loc:
                            logging.warning('cURL fail (location) in WP instance for {}, removing rule'.format(src))
                            continue
                        else:
                            src = loc.pop()
                # Append the star * notation if present in the original rule
                if '*' == orig_src[:-1]:
                    src += '*'
                ext_ruleset.append((src, dst))

                # LANGUAGE EXPANSION
                # GET the URL in all languages (pages)
                if len(lngs) > 1:
                    pages = Utils.csv_filepath_to_dict(self.files[site])
                    # Iterate the pages grouped by number of langs
                    for pi in range(0, len(pages), len(lngs)):
                        page_set = pages[pi:pi + len(lngs)]
                        # FORCE HTTPS in pages (sometimes WP keeps them as HTTP)
                        for p in page_set:
                            p['url'] = p['url'].replace('http://', 'https://')
                        # Check if one of the URLs matches the src
                        matches = [p['url'] for p in page_set if p['url'] == src.strip('*')]
                        if matches:
                            logging.debug('found: {}, {}'.format(src, [p['url'] for p in page_set]))
                            # Remove previous single-lang rule
                            ext_ruleset.pop()
                            # Build the ruleset for all langs and extend the current site rules
                            ruleset = [(p['url'] + ('*' if '*' in orig_src else ''), dst) for p in page_set]
                            ext_ruleset.extend(ruleset)
                            break
            # Replace the current ruleset with the extended version
            self.rulesets[site] = ext_ruleset

        # Sort the rules from most generic to specific or the reverse (-1).
        logging.info('Rule sorting expanded rules: ')
        self._sort_rules()

        return True

    def _sort_rules(self, order=-1):
        for site in self.rulesets:
            self.rulesets[site].sort(key=lambda rule: len(rule[0].split('/')) * order)

    def update_htaccess(self):
        for site in self.rulesets.keys():
            with open(self.site_paths[site] + '/.htaccess', 'r+', encoding='utf8') as f:
                lines = f.readlines()
                rw_base = [l for l in lines if 'RewriteBase ' in l].pop().split(' ').pop().strip()
                f.seek(0, 0)
                f.write('# BEGIN ventilation-redirs\n')
                f.write('RewriteEngine On\n')
                f.write('RewriteBase {}\n'.format(rw_base))
                for (src, dst) in self.rulesets[site]:
                    path = urlparse(src).path[len(rw_base):]
                    f.write('RewriteRule ^{}(.*)$ {}$1 [R=302,L]\n'.format(path, dst))
                f.write('# END ventilation-redirs\n')
                f.write(''.join(lines))

    def apply_filters(self):
        """
        Filter the CSV content per site applying the known filters:
        1) Do not migrate a page or a path (* notation)
        2) Migrate a page or a path (* notation)
        """

        # Get all the rules of *do not migrate* content = right side rule empty
        dont_migrate = [rule[0] for site, rules in self.rulesets.items() for rule in rules if rule[1] == '']

        for site in self.rulesets.keys():
            logging.info('Applying filters for site ' + site)
            # Source CSV files from where to take the content
            csv_f = self.files[site]
            # Get the languages
            lngs = Utils.run_command('wp pll languages --path=' + self.site_paths[site])
            lngs = [l[:2] for l in lngs.split("\n")]
            # Get all the original pages from the CSV
            pages = Utils.csv_filepath_to_dict(csv_f)
            excl_pages = []
            incl_pages = []
            filtered_pages = []
            # Get index-html parent page (if present)
            page_index = ([p['ID'] for p in pages if p['post_name'] == 'index-html'] or ['']).pop()
            logging.debug('page_index ID: {}, for {}'.format(page_index, site))
            for pi in range(0, len(pages), len(lngs)):
                # All pages in all langs
                _pages = pages[pi:pi+len(lngs)]
                # Force HTTPS in all pages
                for p in _pages:
                    p['url'] = p['url'].replace('http://', 'https://')
                # ATTENTION: Selecting the page in EN, if no english is present, then the language at pos. 0
                # will be used (e.g. French)
                p_en = _pages[lngs.index('en')] if 'en' in lngs else _pages[0]

                # Check if the page is marked as do not migrate (assume * notation as well)
                matches = [u.strip('*') for u in dont_migrate if u.strip('*') in p_en['url']]
                if matches:
                    logging.debug('filtering - matches: {}, url: {}'.format(matches, p_en['url']))
                    if self.strict_mode:
                        # Strict mode, exact match. Take sub-paths only if star is present
                        if p_en['url'] == matches[0]:
                            logging.info('[Do not migrate - Strict mode] single match for: {}'.format(p_en['url']))
                            excl_pages.append((p_en['url'], 'don-t migrate'))
                            continue
                        elif (matches[0] + '*') in dont_migrate:
                            logging.info('[Do not migrate - Strict mode *] star match for: {}'.format(p_en['url']))
                            excl_pages.append((p_en['url'], 'don-t migrate *'))
                            continue
                    else:
                        # Greedy mode, [in case]. It's the equivalent of using an implicit * star
                        logging.info('[Do not migrate - Greedy mode] - URL: {}'.format(p_en['url']))
                        excl_pages.append((p_en['url'], 'don-t migrate implicit mode'))
                        continue

                # Check if the page is allowed to be migrated (star * notation and strict mode)
                matches = [r for r in self.rulesets[site] if r[0] and r[0].strip('*') in p_en['url']]
                # Remove the star at the moment, first check for a full match then for a star match
                matches = [(r[0].strip('*'), r[1]) for r in matches]
                if not matches:
                    logging.warning('[Migration - Strict / Greedy mode] no rule for: {}'.format(p_en['url']))
                    excl_pages.append((p_en['url'], 'no rule'))
                else:
                    # Sort matches by specificity (number or URL comps)
                    matches = sorted(matches, key=lambda r: len(r[0].strip('/').split('/')), reverse=True)
                    if self.strict_mode:
                        # Strict mode = exact match. Subpath valid only if star * notation.
                        if matches[0][0] != p_en['url']:
                            # A star * notation might still match the page
                            matches_star = [m for m in matches if m[0] + '*' in list(zip(*self.rulesets[site])).pop(0)]
                            if not matches_star:
                                logging.debug('matches: {} for {}'.format(matches, p_en['url']))
                                logging.info('[Migration - Strict mode *] No single/* match: {}'.format(p_en['url']))
                                excl_pages.append((p_en['url'], 'no strict match'))
                                continue
                            else:
                                incl_pages.append((p_en['url'], matches[0][1] + '*'))
                        else:
                            incl_pages.append((p_en['url'], matches[0][1]))
                    elif matches[0] != p_en['url']:
                        logging.info('[Migration - Greedy mode *] Implicit * match for: {}'.format(p_en['url']))
                        excl_pages.append((p_en['url'], 'no implicit match'))

                # FIX: Current version always has index-html as parent, if it's the case, change it to 0 (root)
                if p_en['post_parent'] == page_index:
                    logging.info('Found index-html as parent of {}, setting it to 0 = root.'.format(p_en['post_name']))
                    for p in _pages:
                        p['post_parent'] = 0

                # Append the filtered pages
                filtered_pages.extend(_pages)

            # Write back the filtered pages to the CSV file
            with open(csv_f, 'w', encoding='utf8') as f:
                """IMPORTANT: The GUID column has to be in the middle or be the last one."""
                header_cols = list(pages[0].keys())
                header_cols.remove('guid')
                header_cols.append('guid')
                writer = csv.DictWriter(f, fieldnames=header_cols)
                writer.writeheader()
                for p in filtered_pages:
                    writer.writerow(p)

            # Export CSV filtered files into a human readable format: JAHIA URL => WP URL
            if self.dry_run:
                base_f, _ = os.path.splitext(csv_f)
                incl_csv = base_f + '_included.csv'
                with open(incl_csv, 'w', encoding='utf8') as f:
                    writer = csv.writer(f)
                    writer.writerow(['SOURCE', 'DESTINATION'])
                    # Translate the WP URLs to JAHIA URLs (extract 1st col)
                    jahia_urls = self._jahia_lookup(site, list(zip(*incl_pages)).pop(0))
                    # Join the JAHIA URLs and the DESTINATION (last col of incl_pages)
                    incl_pages = list(zip(jahia_urls, list(zip(*incl_pages)).pop()))
                    for p in incl_pages:
                        writer.writerow(p)
                excl_csv = base_f + '_excluded.csv'
                with open(excl_csv, 'w', encoding='utf8') as f:
                    writer = csv.writer(f)
                    writer.writerow(['SOURCE', 'REASON'])
                    # Translate the WP URLs to JAHIA URLs (extract 1st col)
                    jahia_urls = self._jahia_lookup(site, list(zip(*excl_pages)).pop(0))
                    # Join the JAHIA URLs and the REASON (last col of excl_pages)
                    excl_pages = list(zip(jahia_urls, list(zip(*excl_pages)).pop()))
                    for p in excl_pages:
                        writer.writerow(p)

        return True

    def _jahia_lookup(self, site, urls=[], only_translated=False):
        wp_conf = self._isvalid_site(site)
        if not wp_conf:
            logging.error('Something weird, site {} should be valid.')
            sys.exit(1)

        # Get hold of the .htaccess file
        htaccess = wp_conf.wp_site.path + '/.htaccess'
        if not os.path.isfile(htaccess):
            logging.error('Cannot find .htaccess file for {}, exiting..'.format(site))
            raise Exception('Cannot find .htaccess file to resolve WP URL to JAHIA URL')

        with open(htaccess, 'r', encoding='utf8') as f:
            lines = list(reversed(f.readlines()))
        logging.info('Retrieved {} lines of .htaccess for {}'.format(len(lines), site))

        jahia_urls = []
        for u in urls:
            # Obtain the GUID
            guid = '/' + u.strip('/').split('/').pop() + '/'
            # By default keep the url if no match found
            jahia_url = None
            if not only_translated:
                jahia_url = u
            # Find the last match in the .htaccess file
            for l in lines:
                comps = l.split(' ')
                if comps.pop().strip() == guid:
                    jahia_url = comps.pop()

            if jahia_url:
                if site not in jahia_url:
                    jahia_url = site + jahia_url
                jahia_urls.append(jahia_url)

        return jahia_urls

    def execute_rules(self):
        """
        Iterate over the rules and start applying them first to the post URL
        """

        # Some stats for different replacement tools:
        # time perl -i -pe's/dcsl/dcsl2/g' dcsl.json;                     # 0m0.007s
        # time sed -i 's/dcsl/dcsl2/g' dcsl.json;                         # 0m0.002s
        # time awk '{gsub("dcsl", "dcsl2")}1' dcsl.json > dcsl.tmp;       # 0m0.002s
        # time python3 regex.py dcsl.json;                                # 0m0.028s
        # regex.py:
        """
        import fileinput
        if __name__ == "__main__":
            with fileinput.input(inplace=1, backup='.bak') as f:
                for line in f:
                    line = line.replace('dcsl','dcsl2')
                    print(line, end='')
        """

        stats = {}
        site_keys = self.rulesets.keys()
        for site in site_keys:
            if site not in stats:
                stats[site] = {}
            for site2 in site_keys:
                # Intersite replacements: Important to make sure that external links coming from other sites point
                # to the right / new location. It will be a NxN check that will be time consuming. That's the reason why
                # it's separated in a different block to let factorise it and add extra options to run it or not.
                if self.context == 'intra' and site != site2:
                    continue
                # Intrasite replacements: All the links inside the site will be replaced
                # according to the URL rules. There is no semantics yet like checking for
                # a ressource existence (e.g. images). A separate structure will be used
                # to map back images to port.
                if self.context == 'inter' and site == site2:
                    continue

                # Target CSV file where to search matches to the rules of the current site.
                csv_f = self.files[site2]
                csv_m = self.medias[site2]
                csv_w = self.widgets[site2]
                # Ruleset for the site, IMPORTANT: the order has to be specific to generic
                ruleset = self.rulesets[site]
                if site2 not in stats[site]:
                    stats[site][site2] = []
                for (source, dest) in ruleset:
                    if source == '':
                        # Do not treat 'create blank page' type rules.
                        continue

                    # Check both protocols, don't trust the source / content
                    matches = {}
                    for prot in ['https', 'http']:
                        source = source.strip('*')
                        _source = source
                        if prot + '://' not in _source:
                            _source = prot + '://' + _source.split('//').pop()
                        cmd = "awk 'END{{print t > \"/dev/stderr\"}}"
                        cmd_m = cmd_w = cmd = cmd + " {{t+=gsub(\"{0}\",\"{1}\")}}1' {2} > {2}.1 && mv {2}.1 {2}"
                        cmd = cmd.format(_source, dest, csv_f)
                        cmd_m = cmd_m.format(_source, dest, csv_m)
                        cmd_w = cmd_w.format(_source, dest, csv_w)
                        logging.debug(cmd, cmd_m, cmd_w)
                        # AWK is counting the replacement occurrences in the stderr.
                        proc = subprocess.run(cmd, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                        subprocess.run(cmd_m, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                        subprocess.run(cmd_w, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                        # This has to be a number normally, but it can also contain return errors, to be analized
                        reps = proc.stderr.strip()
                        matches[prot] = reps
                    try:
                        tot_reps = sum([int(x) for x in matches.values()])
                    except:
                        tot_reps = '; '.join(matches.values())
                    stats[site][site2].append((tot_reps, source, dest, matches))

        return stats

    def execute_rules_guid(self):
        # Replace relative links per site using the GUID of each page as key. Recall, the GUID was converted
        # to a relative link for this purpose.
        dest_sites_keys = self.dest_sites.keys()
        stats = {}
        for site in self.rulesets.keys():
            if site not in stats:
                stats[site] = []
            csv_f = self.files[site]
            csv_w = self.widgets[site]
            pages = Utils.csv_filepath_to_dict(csv_f)
            """ ASSUMPTION: The GUID column is set manually to the last column in the CSV header
            This is useful to replace exactly that column.
            For the href="" attr, the quotes will be used instead. """
            for p in pages:
                guid = p['guid']
                # The postname is the last fragment of the GUID (i.e. relative URL, site/post_name)
                prev_post_name = urlparse(guid).path.strip('/').split('/')[-1]
                # Find the longest matching URL among the target sites
                matches = [s for s in dest_sites_keys if s in p['url']]
                if not matches:
                    logging.warning('No matching destination site for page {}, skipping...'.format(guid))
                else:
                    max_match = '/'.join(max([m.split('/') for m in matches]))
                    logging.debug('{} to {}'.format(p['url'], max_match))
                    # Keep the same post_name if it's the index page
                    if prev_post_name == 'index-html':
                        # This has to be the same otherwise the redirection to the home (/) will not work.
                        post_name = prev_post_name
                    else:
                        # Get the last fragment of the new / migrated URL, that's always the post_name
                        # (e.g. /epfl/research/dcsl/page-1334-en-html, page-1334-en-html is the post_name)
                        post_name = urlparse(p['url']).path.strip('/').split('/')[-1]

                    # URL path is necessary to build the right GUID at the destination (e.g. dirs/site_name/post_name)
                    post_path = urlparse(max_match).path
                    # Use the path and post_name to build the new GUID
                    new_guid = os.path.join(post_path, post_name + '/')
                    # Replace first the GUID column and then the href occurrences in the text
                    cmd = "awk 'END{{print t > \"/dev/stderr\"}}"
                    cmd_body = cmd_widget = cmd = cmd + " {{t+=gsub(\"{0}\",\"{1}\")}}1' {2} > {2}.1 && mv {2}.1 {2}"
                    # Replace the GUID column, use the same quotes to mark the boundaries.
                    cmd = cmd.format(',' + guid, ',' + new_guid, csv_f)
                    # Replace the HTML attrs (e.g. href="") containing the GUID
                    cmd_body = cmd_body.format('\\"' + guid + '\\"', '\\"' + new_guid + '\\"', csv_f)
                    logging.debug(cmd_body)
                    cmd_widget = cmd_widget.format('\\"' + guid + '\\"', '\\"' + new_guid + '\\"', csv_w)
                    # AWK is counting the replacement occurrences in the stderr.
                    subprocess.run(cmd, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                    subprocess.run(cmd_widget, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                    proc_body = subprocess.run(cmd_body, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                    # This has to be a number normally, but it can also contain return errors, to be analized
                    reps = proc_body.stderr.strip()
                    stats[site].append((reps, guid, new_guid, site))

        return stats

    def consolidate_csv(self):
        """
        Create a single CSV file per destination site. This CSV will likely have pages from different source sites
        (e.g. entreprises.epfl.ch and vpi.epfl.ch both go to www.epfl.ch/innovation/...).
        This is also the step where to apply any filters (e.g. strict mode).
        At the same time, the langs at the origin and destination are checked to be sure that they are the same. If
        not, a language can be added with empty post_content (e.g. origin only has EN and dest. has EN/FR) or it can
        be removed (e.g. origin has EN/FR and dest only EN).
        """

        dest_csv = {}
        consolidated_csv_files = []
        dest_sites_keys = self.dest_sites.keys()
        for site in self.rulesets.keys():
            logging.info('Consolidating ' + site)
            # Source CSV files from where to take the content
            csv_f = self.files[site]
            # Get the languages
            lngs = Utils.run_command('wp pll languages --path=' + self.site_paths[site])
            lngs = [l[:2] for l in lngs.split("\n")]
            # Get all the pre-replaced pages from the CSV
            pages = Utils.csv_filepath_to_dict(csv_f)

            prev_max_match = None
            for pi in range(0, len(pages), len(lngs)):
                # All pages in all langs
                _pages = pages[pi:pi+len(lngs)]

                # ATTENTION: Selecting the page in EN, if not then the language at pos. 0 will be used (e.g. French)
                p_en = _pages[lngs.index('en')] if 'en' in lngs else _pages[0]
                logging.info("[en or default] Page {}/{} {}".format(site, p_en['post_name'], p_en['url']))

                # Find the longest matching URL among the target sites
                matches = [s for s in dest_sites_keys if s in p_en['url']]
                if not matches:
                    logging.warning('No matching destination site for page {}, skipping...'.format(p_en['url']))
                else:
                    max_match = '/'.join(max([m.split('/') for m in matches]))
                    # Rename the post if necessary to have a pretty (matching) URL
                    # If there is only one fragment and it's different of the post_name
                    # then change it.
                    fragment = p_en['url'][len(max_match)+1:].strip('/')
                    if fragment:
                        fragment = fragment.split('/').pop()
                    if p_en['post_name'] != fragment and max_match != p_en['url'].strip('/'):
                        p_en['post_name'] = fragment

                    # IMPORTANT: Verify that the source and the destination have the same lang set.
                    dst_path = self.dest_sites[max_match]
                    dst_lngs = Utils.run_command('wp pll languages --path=' + dst_path)
                    dst_lngs = [l[:2] for l in dst_lngs.split("\n")]
                    lngs_curr = lngs
                    if set(dst_lngs) != set(lngs):
                        if prev_max_match != max_match:
                            msg = 'Source {} [{}] and dest. {} [{}] have diff. langs, fixing...'
                            msg = msg.format(site, lngs, max_match, dst_lngs)
                            logging.warning(msg)
                        # Precondition, at least 1 language in common
                        lngs_common = [l for l in lngs if l in dst_lngs]
                        if len(lngs_common) == 0:
                            if prev_max_match != max_match:
                                logging.error('No lang in common between {} and {}, skipping.'.format(site, max_match))
                            continue
                        # Remove any language not in the destination (would not support insert anyways)
                        for idx, l in enumerate(lngs):
                            if l not in dst_lngs:
                                if prev_max_match != max_match:
                                    logging.warning('Lang {} in src but not in dst {}, removing.'.format(l, max_match))
                                del _pages[idx]
                        # Add any missing languages to the source from the destination.
                        new_langs = []
                        for l in dst_lngs:
                            if l not in lngs_common:
                                # duplicate the first post into the new lang without post_content in draft status.
                                msg = 'Duplicating blank {}/{}, in draft for {}...'
                                logging.info(msg.format(site, p_en['post_name'], l))
                                new_p = p_en.copy()
                                new_p['post_content'] = ''
                                new_p['post_status'] = 'draft'
                                new_p['post_name'] += '-' + l
                                _pages.append(new_p)
                                new_langs.append(l)
                        # Join the common and new langs to get the (new) langs at the source
                        lngs_curr = lngs_common + new_langs
                    prev_max_match = max_match

                    # Remove / Change attrs before dumping the post JSON.
                    # _pagesi = [{k: v for k, v in _p.items() if k not in ['ID', 'url']} for _p in _pages]

                    pages_by_lang = {lngs_curr[i]: p for i, p in enumerate(_pages)}
                    # JSON file to contain the post data
                    tmp_json = "/tmp/j2wp_{}_{}.json".format(site.split('/').pop(), _pages[0]['ID'])
                    # IMPORTANT: Keep the pages according to the langs at the destination, consistent polylang
                    # inserts depend on it (i.e. inserts pages as they appear, has no notion of lang order).
                    # Therefore, passing [fr, en] would result in [id1, id2] and [en, fr] will result in [id1, id2].
                    # Due to this, the json is exported manually to keep the order {"lang": page}
                    m = '"{}":{}'
                    arr = [m.format(l, json.dumps(pages_by_lang[l], ensure_ascii=False)) for l in dst_lngs]
                    json_data = '{' + ', '.join(arr) + '}'
                    # Dump the JSON to a file to avoid non escaped char issues.
                    with open(tmp_json, 'w', encoding='utf8') as j:
                        j.write(json_data)

                    # Add the pages to the consolidated collection for the dest site
                    if max_match not in dest_csv:
                        dest_csv[max_match] = []
                    csv_entry = {'url': p_en['url'], 'post_name': p_en['post_name'], 'json_file': tmp_json}
                    dest_csv[max_match].append(csv_entry)

        # Dump a CSV per destination site
        for dst_site_url in dest_csv:
            csv_entries = dest_csv[dst_site_url]

            # Append all *create blank page* rules
            blanks = [r[1] for _, rules in self.rulesets.items() for r in rules if r[0] == '' and dst_site_url in r[1]]
            logging.debug('Blank pages to create for {}: {}'.format(dst_site_url, blanks))
            for blank in blanks:
                csv_entry = {'url': blank, 'post_name': blank.strip('/').split('/').pop(), 'json_file': ''}
                csv_entries.append(csv_entry)

            # Sort csv entries by URL component length, to ease the insertion of parents first
            csv_entries = sorted(csv_entries, key=lambda e: len(e['url'].strip('/').split('/')), reverse=False)
            csv_cons_f = "/tmp/j2wp_consolidated_{}.json".format(dst_site_url.split('//').pop().replace('/', '_'))
            with open(csv_cons_f, 'w', encoding='utf8') as f:
                writer = csv.DictWriter(f, fieldnames=['url', 'post_name', 'json_file'])
                writer.writeheader()
                for e in csv_entries:
                    writer.writerow(e)
            consolidated_csv_files.append(csv_cons_f)

        return consolidated_csv_files

    def migrate_content(self, dst_csv_files):
        """
        ASSUMPTION: All the content is to migrate, there is no content to left behind (e.g. old pages). In any
        case, such content can be removed later in the target destination.
        The migration / insertion of the content is performed by site but it can be parallelised in lots of n
        sites out of the N, the right value is to define while testing according to the available ressources.

        The original code has been changed to iterate on destination sites instead of source sites.

        """

        # Store the new keys per site after the insertion as URL => ID
        # This is useful to set the new parents
        table_ids = {}
        table_ids_url = {}
        media_refs = {}

        for dst_csv_f in dst_csv_files:
            # Get all the pre-prepared hierarchy at destination from the corresponding CSV
            entries = Utils.csv_filepath_to_dict(dst_csv_f)
            if not entries:
                continue
            # IMPORTANT: Sort the list by URL components to insert parents first. Usually done in consolidation phase.
            entries = sorted(entries, key=lambda e: len(e['url'].strip('/').split('/')), reverse=False)

            # Find the dest. site URL as the longuest match of any entry URL (all belong to the same dest.)
            site_url_matches = [s for s in self.dest_sites.keys() if s in entries[0]['url']]
            site_url = '/'.join(max([m.split('/') for m in site_url_matches]))

            # Get the dest site langs
            lngs = Utils.run_command('wp pll languages --path=' + self.dest_sites[site_url])
            lngs = [l[:2] for l in lngs.split("\n")]

            migrate_menu_sidebar = False

            for entry in entries:
                p_url = entry['url']
                json_f = entry['json_file']

                if not json_f:
                    # IMPORTANT: This is a path for a new (blank) page
                    # Last fragment of the URL as post_name and / or post_title (with first letter uppercase)
                    post_name = p_url.strip('/').split('/').pop()
                    post_title = post_name.title()

                    # Associate langs to new BLANK pages
                    blanks = {l: {'post_title': post_title, 'post_status': 'publish', 'post_parent': 0} for l in lngs}
                    # Set the post_parent
                    p_url_parent = p_url.split(post_name).pop(0)
                    if p_url_parent in table_ids_url and p_url_parent.strip('/') != site_url:
                        for idx, l in enumerate(lngs):
                            post_parent = table_ids_url[p_url_parent][idx]
                            blanks[l]['post_parent'] = post_parent

                    # Prepare a JSON as str for the posts in all dest. langs
                    m = '"{}":{}'
                    arr = [m.format(l, json.dumps(blanks[l])) for l in lngs]
                    blanks_json = '{' + ', '.join(arr) + '}'

                    cmd = "echo '{}' | wp pll post create --post_type=page --porcelain --stdin --path={}"
                    cmd = cmd.format(blanks_json, self.dest_sites[site_url])
                    logging.debug(cmd)
                    ids = Utils.run_command(cmd, 'utf8').split(' ')
                    if 'Error' in ids:
                        logging.error('Failed to insert pages. Msg: {}. cmd: {}'.format(ids, cmd))
                    else:
                        logging.info('new IDs {} in lang order {}'.format(ids, lngs))
                        # Keep the new IDs in the URL => IDs dictionary
                        table_ids_url[p_url] = ids
                else:
                    src_site = 'https://{}'.format(json_f.split('_').pop(1))

                    if src_site not in table_ids:
                        table_ids[src_site] = {}
                    # Add the key to the media_refs dict
                    if src_site not in media_refs:
                        media_refs[src_site] = {}

                    # Load the JSON page data
                    with open(json_f, 'r', encoding='utf8') as f:
                        pages = json.load(f)

                    # Old IDs
                    old_ids = [pages[lng]['ID'] for lng in lngs]

                    # Update the parent ID if a new one exists already for the parent URL
                    for lng, p in pages.items():
                        idx_post_name = p_url.rfind(p_url.strip('/').split('/').pop())
                        parent_url = p_url[:idx_post_name]
                        # print('parent_url: ', parent_url, 'p_url', p_url)
                        if p['post_parent'] == '0':
                            # Nothing to do, page already at the root
                            msg = 'Page {}/{} already at the root of the site, no need to change parent'
                            logging.debug(msg.format(src_site, p['post_name']))
                        elif parent_url.strip('/') == site_url:
                            # Set the page at the root: post_parent 0
                            msg = 'Changing parent to the root=0 (old parent [{}]) at {} for post [{}] {}/{}'
                            logging.debug(msg.format(p['post_parent'], site_url, p['ID'], src_site, p['post_name']))
                            p['post_parent'] = 0
                        elif parent_url in table_ids_url:
                            # Update the parent ID based on parent URL
                            p['post_parent'] = table_ids_url[parent_url][lngs.index(lng)]
                            msg = 'Setting parent for page {} to {} [{}]'
                            logging.info(msg.format(p['post_name'], parent_url, p['post_parent']))
                        elif site_url in table_ids[src_site] and p['post_parent'] in table_ids[src_site][site_url]:
                            p['post_parent'] = table_ids[src_site][site_url][p['post_parent']]
                            msg = 'Parent for post {}/{} not derived from URL, keeping same parent with new ID {}'
                            logging.info(msg.format(src_site, p['post_name'], p['post_parent']))
                        else:
                            # Set the page at the root: post_parent 0
                            msg = 'Could not match parent [{}] at {} for post [{}] {}/{}, setting to root=0'
                            logging.warning(msg.format(p['post_parent'], site_url, p['ID'], src_site, p['post_name']))
                            p['post_parent'] = 0
                            # pprint(table_ids_url)
                            # pprint(table_ids)

                    # FIND all the media files in the page content
                    regex = re.compile(r'"(https://[^"]+/wp-content/uploads/.*?)"')
                    for _, p in pages.items():
                        m_urls = regex.findall(p['post_content'])
                        # Verify that all the matched media is under the target domain (site_url)
                        for m_url in m_urls:
                            media_key = m_url[m_url.index('/wp-content/uploads'):]
                            if site_url not in m_url:
                                # Set the right URL
                                _m_url = site_url + media_key
                                p['post_content'] = p['post_content'].replace(m_url, _m_url)
                                logging.debug('Media URL from {} to {}'.format(m_url, _m_url))
                                m_url = _m_url
                            if media_key not in media_refs[src_site]:
                                media_refs[src_site][media_key] = []
                            if m_url not in media_refs[src_site][media_key]:
                                media_refs[src_site][media_key].append(m_url)

                    # Dump the JSON str back for wp-cli, keeping the dest. site lang order
                    m = '"{}":{}'
                    arr = [m.format(l, json.dumps(pages[l], ensure_ascii=False)) for l in lngs]
                    json_data = '{' + ', '.join(arr) + '}'
                    # Dump the JSON to a file to avoid non escaped char issues.
                    with open(json_f, 'w', encoding='utf8') as j:
                        j.write(json_data)

                    cmd = "cat {} | wp pll post create --post_type=page --porcelain --stdin --path={}"
                    cmd = cmd.format(json_f, self.dest_sites[site_url])
                    logging.debug(cmd)
                    ids = Utils.run_command(cmd, 'utf8').split(' ')
                    if 'Error' in ids:
                        logging.error('Failed to insert pages. Msg: {}. cmd: {}'.format(ids, cmd))
                    else:
                        p_info = [src_site + '/' + pages[l]['post_name'] for l in lngs]
                        logging.info('new IDs {} for {} for {}'.format(ids, lngs, p_info))
                        # Keep the new IDs in the URL => IDs dictionary
                        table_ids_url[p_url] = ids
                        # Keep the new IDs also in the table_ids: Site => Dest => IDs
                        if site_url not in table_ids[src_site]:
                            table_ids[src_site][site_url] = {}
                        for old_id, new_id in zip(old_ids, ids):
                            table_ids[src_site][site_url][old_id] = new_id
                        # VERIFY: Setting homepage instead of the default WP options
                        # Using URL in EN by default
                        if site_url == p_url.strip('/'):
                            # Set the menu / sidebar flag
                            migrate_menu_sidebar = True
                            logging.info('Updating home page for site {} to ID {}'.format(site_url, ids[0]))
                            dest_site = self.dest_sites[site_url]
                            cmd = 'wp option update show_on_front page --path={}'.format(dest_site)
                            msg = Utils.run_command(cmd, 'utf8')
                            if 'Success' not in msg:
                                logging.warning('Could not set show_on_front option! Msg: {}. cmd: {}', msg, cmd)
                            cmd = 'wp option update page_on_front {} --path={}'.format(ids[0], dest_site)
                            msg = Utils.run_command(cmd, 'utf8')
                            if 'Success' not in msg:
                                logging.warning('Could not set page_on_front option! Msg: {}. cmd: {}', msg, cmd)

            if migrate_menu_sidebar:
                self.migrate_sidebars(src_site, site_url)
                self.migrate_menu(src_site, site_url, table_ids)

        return media_refs

    def migrate_sidebars(self, site, dst_sidebars_url):
        # Find the widgets page in the CSV
        with open(self.widgets[site], "r", encoding='utf8') as f:
            sidebars_content = yaml.load(f)
        for side_id, widgets in sidebars_content.items():
            for w in widgets:
                # IMPORTANT: The destination sidebars are created while the site is generated.
                # Therefore no need to create them.
                cmd = 'wp widget add {} ' + side_id + ' {} --title="{}" --path={} --text="{}"'
                dst = self.dest_sites[dst_sidebars_url]
                o = w['options']
                # Escape html quotes
                text = o['text'].replace('"', '\\"')
                cmd = cmd.format(w['name'], w['position'], o['title'] + '--' + o['pll_lang'], dst, text)
                # print('sidebar cmd: ' + cmd)
                sidebar_out = Utils.run_command(cmd, 'utf8')
                logging.info('sidebar {} added: {}'.format(side_id, sidebar_out))

        # Set manually the Polylang lang into the widget text since the pll_lang option does not
        # exist in the wp widget add command.
        # Get all the widgets for the site in json format
        widgets = json.loads(Utils.run_command('wp option get widget_text --format=json --path={}'.format(dst)))
        for widget_idx in widgets:
            # If it is a widget (can be just an integer)
            if isinstance(widgets[widget_idx], dict):
                title_comp = widgets[widget_idx]['title'].split('--')
                if len(title_comp) == 2:
                    widgets[widget_idx]['pll_lang'] = title_comp.pop()
                    widgets[widget_idx]['title'] = title_comp.pop()
        # Write back the widget_text option
        widget_f = "/tmp/.tmp_{}_widget_text.json".format(os.path.basename(site))
        with open(widget_f, "w") as f:
            f.write(json.dumps(widgets))
        Utils.run_command('wp option update widget_text --format=json --path={} < {}'.format(dst, widget_f))

    def migrate_menu(self, site, menu_siteurl, table_ids):
        path = self.site_paths[site]
        # Get the langs, the first one is the default
        lngs = Utils.run_command('wp pll languages --path=' + path)
        lngs = [l[:2] for l in lngs.split("\n")]
        # Getting menu list at the source (site)
        cmd = 'wp menu list --fields=slug,locations,term_id --format=json --path={}'.format(path)
        menu_list = Utils.run_command(cmd, 'utf8')
        logging.info('Current menu at source: {}'.format(menu_list))
        if not menu_list:
            logging.error("Cannot get menu list for {}".format(path))
            return
        menu_list = json.loads(menu_list)
        # Construct [location]=>[language]=>[menu term_id]
        loc_lang_menu = {}
        curr_loc = ''
        for menu in menu_list:
            slug = menu['slug'].split('-')
            # The default language is always listed first
            lng = lngs[0]
            if len(slug) == 1:
                curr_loc = menu['locations'][0]
                loc_lang_menu[curr_loc] = {lng: menu['term_id']}
            else:
                lng = slug[1]
                loc_lang_menu[curr_loc][lng] = menu['term_id']
        # Start doing the mapping between the old page IDs and the new IDs at the destination.
        # Do not replace the DB_IDs yet, only after the menu items get inserted at the destination.
        menu_items = {}
        for menu in menu_list:
            fields = 'db_id,type,type_label,position,menu_item_parent,object_id,object,type_label'
            fields += ',link,title,target,attr_title,description,classes,xfn'
            cmd = 'wp menu item list {} --fields={} --format=json --path={}'.format(menu['term_id'], fields, path)
            items_json = Utils.run_command(cmd, 'utf8')
            items = json.loads(items_json)
            for i in range(len(items) - 1, -1, -1):
                item = items[i]
                if item['object'] == 'page':
                    pid = item['object_id']
                    if pid in table_ids[site][menu_siteurl]:
                        new_pid = table_ids[site][menu_siteurl][pid]
                        item['object_id'] = new_pid
                    else:
                        # Remove the menu item
                        logging.info('removing menu item {}, since no new ID at destination'.format(item['db_id']))
                        del items[i]
            menu_items[menu['term_id']] = items
            logging.debug("menu items at source for {}: {}".format(menu['slug'], items_json))

        # Get the langs at the destination, first one is the default, must be EN.
        dst_path = self.dest_sites[menu_siteurl]
        dst_lngs = Utils.run_command('wp pll languages --path=' + dst_path)
        dst_lngs = [l[:2] for l in dst_lngs.split("\n")]
        # Getting menu list at the destination site
        cmd = 'wp menu list --fields=slug,locations,term_id --format=json --path={}'.format(dst_path)
        dst_menu_list = Utils.run_command(cmd, 'utf8')
        if not dst_menu_list:
            logging.error("Cannot get menu list for {}".format(dst_path))
            return
        logging.info('Setting menu for {} at {} '.format(menu_siteurl, dst_path))
        logging.info('Current menu at destination: {}'.format(dst_menu_list))
        # Construct [location]=>[language]=>[menu term_id]
        dst_loc_lang_menu = {}
        curr_loc = ''
        for menu in json.loads(dst_menu_list):
            slug = menu['slug'].split('-')
            # The default language is always listed first
            lng = dst_lngs[0]
            if len(slug) == 1:
                locs = menu['locations']
                if not locs:
                    # Imply the location
                    if menu['slug'] == 'main':
                        locs = ['top']
                    if menu['slug'] == 'footer_nav':
                        locs = ['footer_nav']
                curr_loc = locs[0]
                dst_loc_lang_menu[curr_loc] = {lng: menu['term_id']}
            else:
                lng = slug[1]
                dst_loc_lang_menu[curr_loc][lng] = menu['term_id']
        # print(loc_lang_menu, dst_loc_lang_menu)

        # All the menues are ready to be inserted at the destination.
        # They are ordered by position and parent items appear before. Therefore, the parent
        # menu (db_id) will be updated as they get inserted. This is important since WP automatically
        # creates new db_id entries.
        db_ids = {}
        for loc in loc_lang_menu:
            for lang in loc_lang_menu[loc]:
                # Local term_id
                menu_id = loc_lang_menu[loc][lang]
                items = menu_items[menu_id]
                # Destination menu term_id
                if loc in dst_loc_lang_menu and lang in dst_loc_lang_menu[loc]:
                    dst_menu_id = dst_loc_lang_menu[loc][lang]
                    # Iterate over the individual items inserting them one at a time.
                    for it in items:
                        # Check if a new db_id is assigned to the parent
                        mpid = it['menu_item_parent']
                        if mpid != '0' and mpid in db_ids:
                            print('setting parent id to {} from {}'.format(db_ids[mpid], mpid))
                            it['menu_item_parent'] = db_ids[mpid]
                        fields = '--description={} --attr-title="{}" --target={} --classes="{}" --position={}'
                        fields += ' --parent-id={}'
                        attrs = [it['description'], it['attr_title'], it['target'], it['classes'], it['position']]
                        attrs += [it['menu_item_parent']]
                        if it['object'] == 'page':
                            fields += ' --title="{}"'
                            attrs += [it['title']]
                            fields = fields.format(*attrs)
                            cmd = 'wp menu item add-post {} {} {} --porcelain --path={}'
                            cmd = cmd.format(dst_menu_id, it['object_id'], fields, dst_path)
                        if it['object'] == 'custom':
                            fields = fields.format(*attrs)
                            cmd = 'wp menu item add-custom {} "{}" "{}" {} --porcelain --path={}'
                            cmd = cmd.format(dst_menu_id, it['title'], it['link'], fields, dst_path)
                        new_db_id = Utils.run_command(cmd, 'utf8')
                        # Fix: db_id is an int and menu_item_parent and new_db_id are str
                        db_id = str(it['db_id'])
                        db_ids[db_id] = new_db_id
                else:
                    logging.error('No menu at destination for location {} and lang {}'.format(loc, lang))

        # Force polylang update to display the menu properly
        cmd = 'wp theme list --status=active --field=name --format=csv --path={}'.format(dst_path)
        theme = Utils.run_command(cmd, 'utf8')
        if not theme:
            logging.error('Cannot retrieve current active theme for {}'.format(dst_path))
            return
        nav_menus = {theme: dst_loc_lang_menu}
        # Update polylang option
        cmd = "wp pll option update nav_menus '{}' --path={}".format(json.dumps(nav_menus), dst_path)
        logging.info("nav_menu updated: " + json.dumps(nav_menus))
        logging.info("nav_menu outcome: " + Utils.run_command(cmd, 'utf8'))

    def migrate_media(self, media_refs):
        dest_sites_keys = self.dest_sites.keys()
        for site in self.rulesets.keys():
            # The media are the last to be inserted since it will take longer.
            csv_m = self.medias[site]
            # Get all the pre-replaced media data from the CSV
            wp_medias = Utils.csv_filepath_to_dict(csv_m)
            if not wp_medias:
                logging.warning('No media for site {}, skipping...'.format(site))
                continue
            # Convert it to a dict like: wp_key => wp_media
            up = '/wp-content/uploads/'
            wp_medias = {w['guid'][w['guid'].index(up):]: w for w in wp_medias if up in w['guid']}
            logging.info('Total media files for {}: {}'.format(site, len(wp_medias)))
            logging.info('Total media files referenced in page contents: {}'.format(len(media_refs[site])))
            # print(media_refs)
            for wp_key, wp_media in wp_medias.items():
                # All the sites where to migrate the media file, by default one only.
                wp_media_urls = [wp_media['guid']]
                # Check if media_key is in the references (i.e. if a page points to it)
                if wp_key not in media_refs[site]:
                    msg = 'wp_media {} not ref. by any page, skipping...'.format(wp_key)
                    logging.info(msg)
                    # ATTENTION: Comment the continue line to migrate media to the default WP target site.
                    # msg = ', migrate it to default site anyways {}.'.format(wp_media['guid'])
                    # logging.info(msg)
                    continue
                else:
                    for m_url in media_refs[site][wp_key]:
                        if m_url not in wp_media_urls:
                            wp_media_urls.append(m_url)
                # Try to insert the wp_media in all reference sites (including default / global one)
                for wp_media_url in wp_media_urls:
                    # wp_site before /wp-content, without trailing slash since the inventory doesn't have it either.
                    wp_site = wp_media_url[:wp_media_url.index('/wp-content')]
                    if wp_site not in dest_sites_keys:
                        logging.warning('wp_media {} not migrated to {}, no valid dest site.'.format(wp_key, wp_site))
                        continue
                    # Copy the image physically into the proper dir location
                    # IMPORTANT: If not done, WP would insert into a different location (e.g. ../uploads/2018/06/..)
                    dest_path = self.dest_sites[wp_site] + wp_key
                    dest_dir = os.path.dirname(dest_path)
                    if not os.path.isdir(dest_dir):
                        try:
                            os.mkdirs(dest_dir, exist_ok=True)
                        except:
                            logging.error('Impossible to create dirs {}. Check rights, skipping..'.format(dest_dir))
                            continue
                    try:
                        # Copy all the thumbnails using the * wildcard (e.g. img.jpg and img-150x150.jpg...)
                        media_path = wp_media['file_path']
                        for media_file in glob.glob(os.path.splitext(media_path)[0] + '*'):
                            shutil.copy(media_file, os.path.dirname(dest_path))
                    except Exception as e:
                        logging.error('Cannot copy {} to {}. Err: {}.skipping..'.format(media_path, dest_path, e))
                        continue
                    # Import the media metadata into the target WP dest.
                    # IMPORTANT: --skip-copy will not copy the file, just insert the meta into the DB
                    cmd = 'wp media import {} --title="{}" --caption="{}" --alt="{}" --desc="{}" --path={}'
                    cmd += ' --skip-copy  --porcelain'
                    cmd = cmd.format(dest_path, wp_media['post_title'], '', '', '', self.dest_sites[wp_site])
                    logging.debug('media cmd: ' + cmd)
                    mid = Utils.run_command(cmd, 'utf8')
                    if 'Warning' in mid:
                        logging.warning('Could not import {}. Doing nothing. Warning: {}'.format(dest_path, mid))

    def run_all(self):
        # Check that all source sites are valid WP installs.
        t = tt()
        logging.info('Checking source WP sites for validity')
        sites_errs = self._check_sites(self.rulesets.keys())
        if sites_errs:
            # Can't continue, we have to avoid partial migrations.
            logging.error('Some sites to migrate are not valid: {}, fix it, check /etc/hosts too'.format(sites_errs))
            sys.exit()
        logging.info('[{:.2f}s] Finished checking sites'.format(tt()-t))

        if not self.dest_sites:
            logging.error('Not a single WP instance found at the destination {}!'.format(self.root_wp_dest))
        else:
            logging.info('Explored the destination tree. Found wp instances:')
            pprint(self.dest_sites)
            msg = ' '.join(['Are all the *required* destination WP sites present above?',
                            'You need to create the WP sites trees first (e.g. www.epfl.ch/innovation,',
                            'www.epfl.ch/schools), they\'ll go under /srv/$WP_ENV/www.epfl.ch/...',
                            'Yes/No (y/n) ? : '])
            uinput = input(msg)
            if uinput not in ['Yes', 'y']:
                logging.info('Exiting, please create the tree hierarchy (arborescence).')
                return

        logging.info("{} total sites found in rulesets: ".format(len(self.rulesets)))
        logging.debug(self.rulesets)
        pprint(self.rulesets)

        # Iterate over all the sites to map and dump a CSV with the pages and another
        # one for the media / attachments. This will *greatly simplify* the reinsertion.
        logging.info('CSV dumping...')
        t = tt()
        self.dump_csv()
        logging.info('[{:.2f}s] Finished dumping sites'.format(tt()-t))

        # Translate the JAHIA address into a WP address and also in all the available langs.
        logging.info('Starting rule translation and lang expansion...')
        t = tt()
        self.rule_expansion()
        logging.info('[{:.2f}s] Finished rule translation and lang expansion...'.format(tt()-t))

        # Write the .htaccess redirections for the new URL mapping
        if self.htaccess:
            logging.info('Writing .htaccess file ...')
            self.update_htaccess()

        # Filter the pages using the CSV rules (e.g. do not migrate, strict mode  vs greedy mode)
        logging.info('Starting with the page filtering phase...')
        t = tt()
        self.apply_filters()
        logging.info('[{:.2f}s] Finished with the page filtering phase...'.format(tt()-t))

        if self.dry_run:
            sys.exit(0)

        # At this point all the CSV files are generated and stored by sitename*
        logging.info('Starting rule execution to replace WP URLs... Stats:')
        t = tt()
        stats = self.execute_rules()
        pprint(stats)
        logging.info('[{:.2f}s] Finished rule execution to replace WP URLs...'.format(tt()-t))

        logging.info('Replacing relative WP URLs (both relative GUID and post_name)... Stats:')
        t = tt()
        stats = self.execute_rules_guid()
        pprint(stats)
        logging.info('[{:.2f}s] Finished replacing relative URLs'.format(tt()-t))

        # Consolidate a single CSV per destination, applying filtering (e.g. migrate *, create empty pages..)
        # Also check multilang dest. and if necessary add / remove langs from the source.
        logging.info('Consolidating a single CSV per destination + filtering')
        t = tt()
        dst_csv_files = self.consolidate_csv()
        logging.info('[{:.2f}s] Finished CSV consolidation'.format(tt()-t))

        # At this point all the CSV files have the right URLs in place. It is the moment to effectively migrate
        # the content (pages and files / media)
        logging.info('Preparing insertion in target WP instances (pages, menu, sidebars)...')
        t = tt()
        media_refs = self.migrate_content(dst_csv_files)
        logging.info('[{:.2f}s], Finished insertion in target WP instances'.format(tt()-t))

        logging.info('Preparing media insertion in target WP instances...'.format(tt()-t))
        t = tt()
        self.migrate_media(media_refs)
        logging.info('[{:.2f}s], Finished media insertion in target WP instances...'.format(tt()-t))
