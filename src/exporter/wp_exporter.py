"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import logging
import os
import sys
import re
from parser.box import Box
import timeit
from collections import OrderedDict
from datetime import timedelta, datetime
import json
from bs4 import BeautifulSoup
from wordpress_json import WordpressJsonWrapper, WordpressError

import settings
from exporter.utils import Utils
from utils import Utils as WPUtils
from parser.file import File
from django.utils.text import slugify


class WPExporter:

    # this file is used to save data for importing data
    TRACER_FILE_NAME = "tracer_importing.csv"

    # list of mapping Jahia url and Wordpress url
    urls_mapping = []

    def _build_rest_api_url(self):
        """
        Build the rest API URL of WordPress site
        """
        if self.is_local_environment():
            # when we are in the docker container we must specify the port number
            rest_api_url = "http://{}:8080/".format(self.host)
        else:
            rest_api_url = "https://{}/".format(self.host)

        if self.path:
            rest_api_url += "{}/".format(self.path)

        rest_api_url += "?rest_route=/wp/v2"

        return rest_api_url

    def __init__(self, site, wp_generator, default_language, output_dir=None):
        """
        site is the python object resulting from the parsing of Jahia XML.
        site_host is the domain name.
        site_path is the url part of the site without the site_name.
        output_dir is the path where information files will be generated.
        default_language is the default language for website
        wp_generator is an instance of WP_Generator and is used to call wpcli and admin user info.
        """
        self.site = site
        self.host = wp_generator.wp_site.domain
        self.path = wp_generator.wp_site.folder
        self.elapsed = 0
        self.report = {
            'pages': 0,
            'files': 0,
            'menus': 0,
            'failed_files': 0,
            'failed_menus': 0,
            'failed_widgets': 0,
        }

        self.default_language = default_language

        # dictionary with the key 'wp_page_id' and the value 'wp_menu_id'
        self.menu_id_dict = {}
        self.output_dir = output_dir or settings.JAHIA_DATA_PATH
        self.wp_generator = wp_generator
        self.medias_mapping = {}

        # we use the python-wordpress-json library to interact with the wordpress REST API
        # FIXME : http://<host>/prout/?rest_route=/wp/v2 fonctionne ???
        rest_api_url = self._build_rest_api_url()

        logging.info("setting up API on '%s', with %s:xxxxxx", rest_api_url, wp_generator.wp_admin.username)

        self.wp = WordpressJsonWrapper(rest_api_url, wp_generator.wp_admin.username, wp_generator.wp_admin.password)

    def run_wp_cli(self, command, encoding=sys.getdefaultencoding(), pipe_input=None, extra_options=None):
        """
        Execute a WP-CLI command using method present in WP_Generator instance.

        Argument keywords:
        command -- WP-CLI command to execute. The command doesn't have to start with "wp ".
        encoding -- encoding to use
        """
        return self.wp_generator.run_wp_cli(
            command,
            encoding=encoding,
            pipe_input=pipe_input,
            extra_options=extra_options
        )

    def is_local_environment(self):
        """
        Return True if the host is the HTTPD container name.

        Note: we use the name of HTTPD container to work in local
        """
        return self.host == settings.HTTPD_CONTAINER_NAME

    def import_data_to_wordpress(self, skip_pages=False, skip_media=False):
        """
        Import all data to WordPress via REST API and wp-cli
        """
        try:
            start_time = timeit.default_timer()
            tracer_path = os.path.join(self.output_dir, self.TRACER_FILE_NAME)

            # Allow unfiltered content
            self.run_wp_cli("plugin deactivate EPFL-Content-Filter")

            # Delete the existing widgets to start with an empty sidebar
            self.delete_widgets()

            # media
            if not skip_media:
                self.import_medias()

            # pages
            if not skip_pages:
                self.import_pages()
                self.set_frontpage()

            self.populate_menu()
            self.import_sidebars()
            self.import_breadcrumb()
            self.delete_draft_pages()
            self.display_report()

            # Disallow unfiltered content
            self.run_wp_cli("plugin activate EPFL-Content-Filter")

            # log execution time
            elapsed = timedelta(seconds=timeit.default_timer() - start_time)
            logging.info("Data imported in %s", elapsed)

            # write a csv file
            with open(tracer_path, 'a', newline='\n') as tracer:
                tracer.write("{}, {}, {}, {}, {}, {}\n".format(
                    '{0:%Y-%m-%d %H:%M:%S}'.format(datetime.now()),
                    self.site.name,
                    str(elapsed),
                    self.report['failed_files'],
                    self.report['failed_menus'],
                    self.report['failed_widgets'],
                ))
                tracer.flush()

        except WordpressError as err:
            logging.error("%s - WP export - Exception while importing all data: %s", self.site.name, err)
            with open(tracer_path, 'a', newline='\n') as tracer:
                tracer.write("{}, ERROR {}\n".format(self.site.name, str(err)))
                tracer.flush()
            raise err

    def _asciify_path(self, path):
        """
        Recursive function that takes all files in path and rename them (if needed) with ascii-only characters.
        Recurse in directories found in path (and rename them too if needed). We cannot use os.walk as the renaming
        is done on-the-fly.
        """
        files = []
        dirs = []
        ignored_files = ['thumbnail', 'thumbnail2']
        # Get all files in `path` except those named 'thumbnail' and 'thumbnail2'
        files = [file_name for file_name in os.listdir(path) if os.path.isfile(os.path.join(path, file_name)) and
                 file_name not in ignored_files]
        ignored = ['.', '..']
        # Get all directories in `path`
        dirs = [dir_name for dir_name in os.listdir(path) if not os.path.isfile(os.path.join(path, dir_name)) and
                dir_name not in ignored]
        site_files = []
        for file_name in files:
            try:
                file_name.encode('ascii')
            except UnicodeEncodeError:
                ascii_file_name = file_name.encode('ascii', 'replace').decode('ascii')
                os.rename(os.path.join(path, file_name), os.path.join(path, ascii_file_name))
                file_name = ascii_file_name
            site_files.append(File(name=file_name, path=path))
        for dir_name in dirs:
            try:
                dir_name.encode('ascii')
            except UnicodeEncodeError:
                ascii_dir_name = dir_name.encode('ascii', 'replace').decode('ascii')
                os.rename(os.path.join(path, dir_name), os.path.join(path, ascii_dir_name))
                dir_name = ascii_dir_name
            # Recurse on each directory
            site_files.extend(self._asciify_path(os.path.join(path, dir_name)))
        return site_files

    def import_medias(self):
        """
        Import medias to Wordpress
        """
        logging.info("WP medias import start")
        self.run_wp_cli('cap add administrator unfiltered_upload')

        # No point if there are no files (apc site has no files for example)
        if self.site.files:
            start = "{}/content/sites/{}/files".format(self.site.base_path, self.site.name)
            self.site.files = self._asciify_path(start)

            count = 0
            for file in self.site.files:
                wp_media = self.import_media(file)
                if wp_media:
                    self.fix_file_links(file, wp_media)
                    self.report['files'] += 1
                    count += 1

                    if count % 10 == 0:
                        logging.info("[%s/%s] WP medias imported", self.report['files'], len(self.site.files))

            self.fix_key_visual_boxes()
        # Remove the capability "unfiltered_upload" to the administrator group.
        self.run_wp_cli('cap remove administrator unfiltered_upload')
        logging.info("%s WP medias imported", self.report['files'])

    def import_media(self, media):
        """
        Import a media to Wordpress
        """
        # Try to encode the path in ascii, if it fails then the path contains non-ascii characters.
        # In that case convert to ascii with 'replace' option which replaces unknown characters by '?',
        # and rename the file with that new name.
        file_path = os.path.join(media.path, media.name)
        size = os.path.getsize(file_path)

        # If the file is empty, do not try to import
        if size == 0:
            logging.warning('Media %s is empty', file_path)
            self.report['failed_files'] += 1
            return None
        # If the file is too big, do not try to import
        elif size > settings.UPLOAD_MAX_FILESIZE:
            logging.warning('Media %s is too big. Size: %s', file_path, size)
            self.report['failed_files'] += 1
            return None

        file = open(file_path, 'rb')

        files = {
            'file': file
        }

        wp_media_info = {
            # date
            # date_gmt
            'slug': media.path,
            # status
            'title': media.name,
            # author
            # comment_status
            # ping_status
            # meta
            # template
            # alt_text
            # caption
            # description
            # post
        }
        files = files
        try:
            logging.debug("WP media information %s", wp_media_info)
            wp_media = self.wp.post_media(data=wp_media_info, files=files)
            return wp_media
        except Exception as e:
            logging.error("%s - WP export - media failed: %s", self.site.name, e)
            self.report['failed_files'] += 1
            raise e

    def import_breadcrumb(self):
        """
        Import breadcrumb in default language by setting correct option in DB
        """

        # If there is a custom breadrcrumb defined for this site and the default language
        if self.site.breadcrumb_title and self.site.breadcrumb_url and \
                self.default_language in self.site.breadcrumb_title and \
                self.default_language in self.site.breadcrumb_url:
            # Generatin breadcrumb to save in parameters
            breadcrumb = "[EPFL|www.epfl.ch]"
            breadcrumb_titles = self.site.breadcrumb_title[self.default_language]
            breadcrumb_urls = self.site.breadcrumb_url[self.default_language]
            for breadcrumb_title, breadcrumb_url in zip(breadcrumb_titles, breadcrumb_urls):
                breadcrumb += ">[{}|{}]".format(breadcrumb_title, breadcrumb_url)

            self.run_wp_cli("option update epfl:custom_breadcrumb '{}'".format(breadcrumb))

    def fix_file_links(self, file, wp_media):
        """
        Fix the links pointing to the given file. Following elements are processed:
        - All boxes
        - All banners (headers)
        - Shortcodes
        """

        if "/files" not in file.path:
            return

        # the old url is the file relative path
        old_url = file.path[file.path.rfind("/files"):]

        # the new url is the wp media source url
        new_url = wp_media['source_url']
        self.medias_mapping[new_url] = wp_media['id']

        tag_attribute_tuples = [("a", "href"), ("img", "src"), ("script", "src"), ("source", "src")]

        # 1. Looping through boxes
        for box in self.site.get_all_boxes():

            # first fix in shortcodes
            self.fix_file_links_in_shortcode_attributes(box, old_url, new_url)

            soup = BeautifulSoup(box.content, 'html5lib')
            soup.body.hidden = True

            # fix in html tags
            for tag_name, tag_attribute in tag_attribute_tuples:
                self.fix_links_in_tag(
                    soup=soup,
                    old_url=old_url,
                    new_url=new_url,
                    tag_name=tag_name,
                    tag_attribute=tag_attribute)

            # save the new box content
            box.content = str(soup.body)

        # 2. Menus
        self.fix_file_links_in_menus(old_url, new_url)

        # 3. Looping through banners
        for lang, banner in self.site.banner.items():

            soup = BeautifulSoup(banner.content, 'html.parser')

            for tag_name, tag_attribute in tag_attribute_tuples:
                self.fix_links_in_tag(
                    soup=soup,
                    old_url=old_url,
                    new_url=new_url,
                    tag_name=tag_name,
                    tag_attribute=tag_attribute)

            # save the new banner content
            banner.content = str(soup)

    def fix_file_links_in_menu_items(self, menu_item, old_url, new_url):
        if menu_item.points_to_file():
                normalized_url = menu_item.points_to.encode('ascii', 'replace').decode('ascii').replace('?', '')
                normalized_url = normalized_url[normalized_url.rfind("/files"):]
                if normalized_url == old_url.replace('?', ''):
                    menu_item.points_to = new_url

    def fix_file_links_in_menus(self, old_url, new_url):
        for lang in self.site.languages:
            for root_entry_index, menu_item in enumerate(self.site.menus[lang]):
                self.fix_file_links_in_menu_items(menu_item, old_url, new_url)
                self.fix_file_links_in_submenus(menu_item, old_url, new_url)

    def fix_file_links_in_submenus(self, menu_item, old_url, new_url):
        for child in menu_item.children:
            self.fix_file_links_in_menu_items(child, old_url, new_url)
            self.fix_file_links_in_submenus(child, old_url, new_url)

    def fix_page_links_in_sidebar(self, site_folder):
        """
        Fix page links in sidebar widgets
        :param site_folder: path to folder containing website files
        :return:
        """
        logging.info("Fixing sidebar content links")
        for lang in self.site.homepage.contents.keys():

            for box in self.site.homepage.contents[lang].sidebar.boxes:

                soup = BeautifulSoup(box.content, 'html5lib')
                soup.body.hidden = True

                for url_mapping in self.urls_mapping:
                    new_url = "{}/{}/".format(site_folder, url_mapping["wp_slug"])
                    for old_url in url_mapping["jahia_urls"]:
                        self.fix_links_in_tag(
                            soup=soup,
                            old_url=old_url,
                            new_url=new_url,
                            tag_name="a",
                            tag_attribute="href"
                        )

                box.content = str(soup.body)

    def fix_page_links_in_pages(self, wp_pages, site_folder):
        """
        Fix all the links once we know all the WordPress pages urls
        :param wp_pages: list of pages to fix
        :param site_folder: path to folder containing website files
        :return:
        """
        logging.info("Fixing page content links")

        for wp_page in wp_pages:

            content = ""

            if "content" in wp_page:
                content = wp_page["content"]["raw"]
            else:
                logging.error("Expected content for page %s", wp_page)

            # Step 1 - Fix in shortcode attributes
            # We loop 2 times through self.urls_mapping because the first time we modify directly HTML content
            # and the second time, we fix links in HTML tags and we use Beautiful Soup to do this.
            for url_mapping in self.urls_mapping:
                # Generating new URL from slug
                new_url = "{}/{}/".format(site_folder, url_mapping["wp_slug"])

                for old_url in url_mapping["jahia_urls"]:

                    for shortcode, attributes_list in self.site.shortcodes.items():

                        search = re.compile('\[{} [^\]]*\]'.format(shortcode))

                        # Looping through founded shortcodes
                        # ex: [epfl_infoscience url="<url>"]
                        for code in search.findall(content):
                            old_code = code
                            # Looping through shortcodes attributes to update
                            for attribute in attributes_list:
                                # <query> in regex is to handle URL like this :
                                # .../path/to/page#tag
                                # .../path/to/page?query=string
                                old_regex = re.compile('{}="(http(s)?://{})?{}(?P<query> [^"]*)"'.format(
                                    attribute,
                                    re.escape(self.site.server_name),
                                    re.escape(old_url)), re.VERBOSE)

                                # To build "new" URL and still having the "end" of the old URL (#tag, ?query=string)
                                new_regex = r'{}="{}\g<query>"'.format(attribute, new_url)

                                # Update attribute in shortcode
                                code = old_regex.sub(new_regex, code)

                            # Replace shortcode with the one updated with new urls
                            content = content.replace(old_code, code)

            soup = BeautifulSoup(content, 'html5lib')
            soup.body.hidden = True

            # Step 2 - Fix in HTML tags
            for url_mapping in self.urls_mapping:
                new_url = "{}/{}/".format(site_folder, url_mapping["wp_slug"])
                for old_url in url_mapping["jahia_urls"]:
                    self.fix_links_in_tag(
                        soup=soup,
                        old_url=old_url,
                        new_url=new_url,
                        tag_name="a",
                        tag_attribute="href"
                    )

            # update the page
            wp_id = wp_page["id"]

            content = str(soup.body)

            self.update_page_content(page_id=wp_id, content=content)

    def fix_file_links_in_shortcode_attributes(self, box, old_url, new_url):
        """
        Fix the link in a box shortcode for all registered attributes.

        This will replace for example:

        image="/files/51_recyclage/vignette_bois.png"

        to:

        image="/wp-content/uploads/2018/04/vignette_bois.png"
        """
        for attribute in box.shortcode_attributes_to_fix:
            old_attribute = '{}="{}"'.format(attribute, old_url)
            new_attribute = '{}="{}"'.format(attribute, new_url)

            box.content = box.content.replace(old_attribute, new_attribute)

    def fix_links_in_tag(self, soup, old_url, new_url, tag_name, tag_attribute):
        """Fix the links in the given HTML tag"""

        tags = soup.find_all(tag_name)

        pid = ""
        # If the old url points to a jahia page
        if '/page-' in old_url:
            # Try to get the PID of the page from the URL (usually jahia URLs are of the form
            # /page-{PID}-{lang}.html
            try:
                pid = old_url.split("-")[1]
            except IndexError:
                pass

        for tag in tags:
            link = tag.get(tag_attribute)

            if not link:
                continue

            # Encoding in the export file (export_<lang>.xml) and encoding of the filenames
            # in the zip are not the same. string.encode('ascii', 'replace') replaces all
            # unknown characters by '?'. What happens here is that for a file named 'vidéo.mp4',
            # the old_url, which is actually the path on the file system, will be 'vid??o.mp4'
            # once converted to ascii; but, the links that reference the media in the export file
            # will be converted to 'vid?o.mp4'.
            # So we convert to ascii and remove the '?' character to compare the strings and see
            # if there is a link to replace.
            # If the current link is a page PID and corresponds to the PID extracted from old_url then
            # point the link to the new url of the page.
            if link.encode('ascii', 'replace').decode('ascii').replace('?', '') == old_url.replace('?', '') \
                    or (pid and link == pid):
                logging.debug("Changing link from %s to %s", (old_url, new_url))
                tag[tag_attribute] = new_url

    def fix_key_visual_boxes(self):
        """[su_slider source="media: 1650,1648,1649" title="no" arrows="yes"]"""
        for box in self.site.get_all_boxes():
            if box.type == Box.TYPE_KEY_VISUAL:
                soup = BeautifulSoup(box.content, 'html.parser')
                medias_ids = []
                for img in soup.find_all("img"):
                    if img['src'] in self.medias_mapping:
                        medias_ids.append(self.medias_mapping[img['src']])
                box.content = '[su_slider source="media: {}"'.format(','.join([str(m) for m in medias_ids]))
                box.content += ' title="no" arrows="yes"]'

    def update_page(self, page_id, content, title=None):
        """
        Import a page to Wordpress
        """
        wp_page_info = {
            # date: auto => date/heure du jour
            # date_gmt: auto => date/heure du jour GMT
            # 'slug': slug,
            # 'status': 'publish',
            # password
            'content': content,
            # author
            # excerpt
            # featured_media
            # comment_status: 'closed'
            # ping_status: 'closed'
            # format
            # meta
            # sticky
            # template
            # categories
            # tags
        }

        if title:
            wp_page_info['title'] = title

        return self.wp.post_pages(page_id=page_id, data=wp_page_info)

    def update_page_content(self, page_id, content):
        """Update the page content"""
        return self.update_page(page_id, content)

    def import_pages(self):
        """
        Import all pages of jahia site to Wordpress
        """

        # keep the pages for fixing the links later
        wp_pages = []

        for page in self.site.pages_by_pid.values():

            # We have to use OrderedDict to avoid bad surprises when page has only one language. Sometimes, Dict isn't
            # taken in the "correct" order and we try to modify page which has been deleted because no translation. So
            # it was editing a page which was in the Trash.
            contents = OrderedDict()
            info_page = OrderedDict()

            for lang in page.contents.keys():
                contents[lang] = ""

                # create the page content
                for box in page.contents[lang].boxes:

                    if not box.is_shortcode():
                        contents[lang] += '<div class="{}">'.format(box.type + "Box")

                    if box.title:
                        if WPUtils.is_html(box.title):
                            contents[lang] += '<h3>{0}</h3>'.format(box.title)
                        else:
                            slug = slugify(box.title)
                            contents[lang] += '<h3 id="{0}">{0}</h3>'.format(slug, box.title)

                    # in the parser we can't know the current language.
                    # we assign a string that we replace with the current language
                    if box.type in (Box.TYPE_PEOPLE_LIST, Box.TYPE_MAP):
                        if Box.UPDATE_LANG in box.content:
                            box.content = box.content.replace(Box.UPDATE_LANG, lang)

                    contents[lang] += box.content

                    if not box.is_shortcode():
                        contents[lang] += "</div>"

                info_page[lang] = {
                    'post_name': page.contents[lang].path,
                    'post_status': 'publish',
                }

            # If the page doesn't exist for all languages on the site we create a blank page in draft status
            # At the end of export we delete all draft pages
            for lang in self.wp_generator._site_params['langs'].split(","):
                if lang not in info_page:
                    contents[lang] = ""
                    info_page[lang] = {
                        'post_name': '',
                        'post_status': 'draft'
                    }

            cmd = "pll post create --post_type=page --stdin --porcelain"
            stdin = json.dumps(info_page)

            result = self.run_wp_cli(cmd, pipe_input=stdin)
            if not result:
                error_msg = "Could not created page"
                logging.error(error_msg)
                raise Exception(error_msg)

            wp_ids = result.split()

            if len(wp_ids) != len(contents):
                error_msg = "{} page created is not expected : {}".format(len(wp_ids), len(contents))
                logging.error(error_msg)
                raise Exception(error_msg)

            # Delete draft pages as soon as possible to prevent them from being problems
            self.delete_draft_pages()

            for wp_id, (lang, content) in zip(wp_ids, contents.items()):
                # If page doesn't exists for current lang (but it was created as draft before and then deleted),
                # we skip the update (because there is nothing to update and we don't have needed information...
                if lang not in page.contents:
                    continue

                # Updating page in WordPress
                wp_page = self.update_page(page_id=wp_id, title=page.contents[lang].title, content=content)

                # prepare mapping for htaccess redirection rules
                mapping = {
                    'jahia_urls': page.contents[lang].vanity_urls,
                    'wp_slug': wp_page['slug']
                }

                self.urls_mapping.append(mapping)

                logging.info("WP page '%s' created", wp_page['slug'])

                # keep WordPress ID for further usages
                page.contents[lang].wp_id = wp_page['id']

                wp_pages.append(wp_page)

            self.report['pages'] += 1

        if self.wp_generator.wp_site.folder == "":
            site_folder = ""
        else:
            site_folder = "/{}".format(self.wp_generator.wp_site.folder)

        # Update page links in all imported pages
        self.fix_page_links_in_pages(wp_pages, site_folder)

        # Update page links in sidebar boxes
        self.fix_page_links_in_sidebar(site_folder)

        self.create_sitemaps()

        self.update_parent_ids()

    def update_parent_ids(self):
        """
        Update all pages to define the pages hierarchy
        """
        for page in self.site.pages_by_pid.values():
            for lang, page_content in page.contents.items():

                # If page has parent (is not homepage)
                # AND parent is not homepage
                # AND we have an ID for its content,
                if page.parent and not page.parent.is_homepage() and page_content.wp_id:
                    # We use the page parent id to update it in WordPress
                    wp_page_info = {
                        'parent': page.parent.contents[lang].wp_id
                    }
                    self.wp.post_pages(page_id=page.contents[lang].wp_id, data=wp_page_info)

    def create_sitemaps(self):

        info_page = OrderedDict()

        for lang in self.site.homepage.contents.keys():
            # create sitemap page

            info_page[lang] = {
                'post_name': 'sitemap',
                'post_status': 'publish',
            }

        cmd = "pll post create --post_type=page --stdin --porcelain"
        stdin = json.dumps(info_page)
        result = self.run_wp_cli(command=cmd, pipe_input=stdin)

        sitemap_ids = result.split()
        for sitemap_wp_id, lang in zip(sitemap_ids, info_page.keys()):
            wp_page = self.update_page(
                page_id=sitemap_wp_id,
                title='sitemap',
                content='[simple-sitemap show_label="false" types="page" orderby="menu_order"]'
            )
            self.create_footer_menu_for_sitemap(sitemap_wp_id=wp_page['id'], lang=lang)

    def import_sidebars(self):
        """
        Import sidebars via wpcli
        Sidebars are :
        - homepage sidebar
        - header sidebar (if site has custom banner).
        All sidebars are imported in this function because we then have to se correct language for each sidebar widget
        and doing everything in one place is more simple.
        """
        def prepare_html(html):
            return Utils.escape_quotes(html.replace(u'\xa0', u' '))

        widget_pos = 1
        widget_pos_to_lang = {}

        try:
            # First, we import banners if exists
            # Banner is only one text widget per lang in a dedicated sidebar
            for lang, banner in self.site.banner.items():

                if not banner.content:
                    logging.warning("Banner is empty")
                    continue

                cmd = 'widget add text header-widgets --text="{}"'.format(
                    banner.content.replace('"', '\\"'))

                self.run_wp_cli(cmd)
                widget_pos_to_lang[str(widget_pos)] = lang
                widget_pos += 1

                logging.info("Banner imported for '%s' language", lang)

            # Then we import sidebar widgets
            for lang in self.site.homepage.contents.keys():

                for box in self.site.homepage.contents[lang].sidebar.boxes:
                    if box.type in [Box.TYPE_TEXT, Box.TYPE_CONTACT, Box.TYPE_LINKS, Box.TYPE_FILES]:
                        widget_type = 'text'
                        title = prepare_html(box.title)
                        content = prepare_html(box.content)

                    elif box.type == Box.TYPE_COLORED_TEXT:
                        widget_type = 'text'
                        title = ""
                        content = "[colored-box]"
                        content += prepare_html("<h3>{}</h3>".format(box.title))
                        content += prepare_html(box.content)
                        content += "[/colored-box]"

                    # Box type not supported for now,
                    else:
                        logging.warning("Box type currently not supported for sidebar (%s)", box.type)
                        widget_type = 'text'
                        title = prepare_html("TODO: {}".format(box.title))
                        content = prepare_html(box.content)

                    cmd = 'widget add {} page-widgets {} --text="{}" --title="{}"'.format(
                        widget_type,
                        widget_pos,
                        WPUtils.clean_html_comments(content),
                        title
                    )

                    self.run_wp_cli(cmd)

                    # Saving widget position for current widget (as string because this is a string that is
                    # used to index information in DB)
                    widget_pos_to_lang[str(widget_pos)] = lang
                    widget_pos += 1

                logging.info("WP sidebar imported for '%s' language", lang)

            # If widgets were added
            if widget_pos_to_lang:
                # Getting existing 'text' widget list
                widgets = json.loads(self.run_wp_cli('option get widget_text --format=json'))

                # Looping through widget to apply correct lang
                for widget_index in widgets:
                    # If it is a widget (can be just an integer)
                    if isinstance(widgets[widget_index], dict):
                        # Set correct lang
                        widgets[widget_index]['pll_lang'] = widget_pos_to_lang[widget_index]

                # Create unique file to save JSON to update widget languages
                filename = "{}.json".format(self.site.name)
                with open(filename, "wb") as f_json:
                    widget_json = json.dumps(widgets)
                    f_json.write(widget_json.encode('utf-8'))
                    f_json.flush()

                # Updating languages for all widgets
                self.run_wp_cli('option update widget_text --format=json < {}'.format(filename))

                os.remove(filename)

        except WordpressError as e:
            logging.error("%s - WP export - widget failed: %s", self.site.name, e)
            self.report['failed_widgets'] += 1
            raise e

    def create_footer_menu_for_sitemap(self, sitemap_wp_id, lang):
        """
        Create footer menu for sitemap page
        """

        def clean_menu_html(cmd):
            return cmd.replace('\u200b', '')

        # FIXME: add an attribut default_language to wp_generator.wp_site class
        default_language = self.wp_generator._site_params['langs'].split(",")[0]
        if default_language == lang:
            footer_name = settings.FOOTER_MENU
        else:
            footer_name = "{}-{}".format(settings.FOOTER_MENU, lang)

        self.run_wp_cli('menu item add-post {} {} --porcelain'.format(footer_name, sitemap_wp_id))

        # Create footer menu
        cmd = "menu item add-custom {} Accessibility http://www.epfl.ch/accessibility.en.shtml​".format(footer_name)
        cmd = clean_menu_html(cmd)
        self.run_wp_cli(cmd)

        # legal notice
        cmd = "menu item add-custom {} 'Legal Notice' http://mediacom.epfl.ch/disclaimer-en".format(footer_name)
        cmd = clean_menu_html(cmd)
        self.run_wp_cli(cmd)

        # Report
        self.report['menus'] += 2

    def create_submenu(self, parent_page, parent_menu_item, lang, menu_name, parent_menu_id):
        """
        Create recursively submenus for one main menu entry

        parent_page - parent page for which we have to create submenu
        parent_menu_item - MenuItem object coming from self.menus and representing parent of submenu entries to create
        lang - language
        menu_name - name of WP menu where to put sub-menu entries
        parent_menu_id - ID of parent menu (in WP) of submenu we have to create
        """

        # If the sub-entries are sorted
        if parent_menu_item.children_sort_way is not None:
            # Sorting information in the other structure storing the menu information
            parent_page.children.sort(key=lambda x: x.contents[lang].title,
                                      reverse=(parent_menu_item.children_sort_way == 'desc'))

        for sub_entry_index, menu_item in enumerate(parent_menu_item.children):

            # If entry is visible
            if not menu_item.hidden:

                # If menu entry is an hardcoded URL
                if menu_item.points_to_url() or menu_item.points_to_sitemap():

                    # Recovering URL
                    url = menu_item.points_to

                    # If menu entry is sitemap
                    # OR
                    # If points to an anchor on a page, URL is not is absolute (starts with 'http').
                    # If URL is not absolute, this is because it points to a vanity URL defined in Jahia
                    # THEN we add WP site base URL
                    if menu_item.points_to_sitemap() or \
                            (menu_item.points_to_anchor() and not url.startswith('http')):
                        url = "{}/{}".format(self.wp_generator.wp_site.url, url)

                    # Generate target information if exists
                    target = "--target={}".format(menu_item.target) if menu_item.target else ""

                    cmd = 'menu item add-custom {} "{}" "{}" {} --parent-id={} --porcelain' \
                        .format(menu_name, menu_item.txt.replace('"', '\\"'), url, target, parent_menu_id)

                    menu_id = self.run_wp_cli(cmd)
                    if not menu_id:
                        logging.warning("Root menu item not created for URL (%s) ", url)
                    else:
                        self.report['menus'] += 1

                # menu entry is page
                else:
                    # Trying to get corresponding page corresponding to current page UUID
                    child = self.site.homepage.get_child_with_uuid(menu_item.points_to, 4)

                    if child is None:
                        logging.error("Submenu creation: No page found for UUID %s", menu_item.points_to)
                        continue

                    if lang in child.contents and child.parent.contents[lang].wp_id in self.menu_id_dict and \
                            child.contents[lang].wp_id:  # FIXME For unknown reason, wp_id is sometimes None

                        command = 'menu item add-post {} {} --title="{}" --parent-id={} --porcelain' \
                            .format(menu_name,
                                    child.contents[lang].wp_id,
                                    child.contents[lang].menu_title.replace('"', '\\"'),
                                    parent_menu_id)

                        menu_id = self.run_wp_cli(command)
                        if not menu_id:
                            logging.warning("Menu not created for page %s", child.pid)
                        else:
                            self.menu_id_dict[child.contents[lang].wp_id] = Utils.get_menu_id(menu_id)
                            self.report['menus'] += 1

                        self.create_submenu(child,
                                            menu_item,
                                            lang,
                                            menu_name,
                                            self.menu_id_dict[child.contents[lang].wp_id])

    def populate_menu(self):
        """
        Add pages into the menu in wordpress.
        This menu was created when configuring the polylang plugin.
        """
        logging.info("Populating menu...")
        try:
            # Create homepage menu
            for lang, page_content in self.site.homepage.contents.items():

                # FIXME: add an attribut default_language to wp_generator.wp_site class
                default_language = self.wp_generator._site_params['langs'].split(",")[0]
                if default_language == lang:
                    menu_name = settings.MAIN_MENU
                else:
                    menu_name = "{}-{}".format(settings.MAIN_MENU, lang)

                # FIXME For unknown reason, wp_id is sometimes None
                if page_content.wp_id:
                    # Create root menu 'home' entry (with the house icon)
                    cmd = 'menu item add-post {} {} --classes=link-home --porcelain'.format(
                        menu_name,
                        page_content.wp_id
                    )
                    menu_id = self.run_wp_cli(cmd)
                    if not menu_id:
                        logging.warning("Home root menu not created for page  %s", page_content.pid)
                    else:
                        self.menu_id_dict[page_content.wp_id] = Utils.get_menu_id(menu_id)
                        self.report['menus'] += 1

                # In the following loop, we will have two differents sources for menu entries and their children.
                # One is "self.site.menus[lang]" and is containing all the root menus and their submenus.
                # Those menus entries are for existing WordPress pages OR are hardcoded URLs OR references to
                # other pages already pointed by another menu entry.
                # For hardcoded URL, the URL has been recovered in the parser and is present in the structure.
                # For WordPress pages and references, we have info about menu title and page uuid.
                # The other is "self.site.homepage.children" and is containing pages and subpages existing in
                # WordPress (used to build the menu) but we don't have any information about hardcoded URL here.
                # So, all the information we need to create the menu is splitted between two different sources...
                # and the goal of the following loop is to go through the first structure (which contains all the
                # menu entries) and every time we encounter a WordPress page, we look for the corresponding item in
                # the second list (which contains information about pointed page id in WP).

                # Looping through root menu entries
                for root_entry_index, menu_item in enumerate(self.site.menus[lang]):

                    # If root entry is visible
                    if not menu_item.hidden:

                        # If root menu entry is an hardcoded URL
                        # OR a sitemap link
                        if menu_item.points_to_url() or \
                                menu_item.points_to_sitemap():

                            # Recovering URL
                            url = menu_item.points_to

                            # If menu entry is sitemap
                            # OR
                            # If points to an anchor on a page, URL is not is absolute (starts with 'http').
                            # If URL is not absolute, this is because it points to a vanity URL defined in Jahia
                            # THEN we add WP site base URL
                            if menu_item.points_to_sitemap() or \
                                    (menu_item.points_to_anchor() and not url.startswith('http')):
                                url = "{}/{}".format(self.wp_generator.wp_site.url, url)

                            # Generate target information if exists
                            target = "--target={}".format(menu_item.target) if menu_item.target else ""

                            cmd = 'menu item add-custom {} "{}" "{}" {} --porcelain' \
                                .format(menu_name, menu_item.txt.replace('"', '\\"'), url, target)

                            menu_id = self.run_wp_cli(cmd)
                            if not menu_id:
                                logging.warning("Root menu item not created for URL (%s) ", url)
                            else:
                                self.report['menus'] += 1

                        # root menu entry is pointing to a page
                        else:
                            # Trying to get corresponding page corresponding to current page UUID
                            homepage_child = self.site.homepage.get_child_with_uuid(menu_item.points_to, 3)

                            if homepage_child is None:
                                logging.error("Menu creation: No page found for UUID %s", menu_item.points_to)
                                continue

                            if lang not in homepage_child.contents:
                                logging.warning("Page not translated %s", homepage_child.pid)
                                continue

                            if homepage_child.contents[lang].wp_id:

                                cmd = 'menu item add-post {} {} --title="{}" --porcelain' \
                                      .format(menu_name,
                                              homepage_child.contents[lang].wp_id,
                                              homepage_child.contents[lang].menu_title.replace('"', '\\"'))
                                menu_id = self.run_wp_cli(cmd)
                                if not menu_id:
                                    logging.warning("Root menu item not created %s for page ", homepage_child.pid)
                                else:
                                    self.menu_id_dict[homepage_child.contents[lang].wp_id] = Utils.get_menu_id(menu_id)
                                    self.report['menus'] += 1

                                # create recursively submenus
                                self.create_submenu(homepage_child,
                                                    menu_item,
                                                    lang,
                                                    menu_name,
                                                    self.menu_id_dict[homepage_child.contents[lang].wp_id])

                logging.info("WP menus populated for '%s' language", lang)

        except Exception as e:
            logging.error("%s - WP export - menu failed: %s", self.site.name, e)
            self.report['failed_menus'] += 1
            raise e

    def set_frontpage(self):
        """
        Use wp-cli to set the two WordPress options needed for the job
        """
        # sanity check on homepage
        if not self.site.homepage:
            raise Exception("No homepage defined for site")

        # call wp-cli
        self.run_wp_cli('option update show_on_front page')

        if self.default_language in self.site.homepage.contents.keys():
            frontpage_id = self.site.homepage.contents[self.default_language].wp_id
            result = self.run_wp_cli('option update page_on_front {}'.format(frontpage_id))
            if result is not None:
                # Set on only one language is sufficient
                logging.info("WP frontpage setted")

    def delete_all_content(self):
        """
        Delete all content WordPress
        """
        self.delete_medias()
        self.delete_pages()
        self.delete_widgets()

    def delete_medias(self):
        """
        Delete medias in WordPress via WP REST API
        HTTP delete  http://.../wp-json/wp/v2/media/1761?force=true
        """
        logging.info("Deleting medias...")
        medias = self.wp.get_media(params={'per_page': '100'})
        while len(medias) != 0:
            for media in medias:
                self.wp.delete_media(media_id=media['id'], params={'force': 'true'})
            medias = self.wp.get_media(params={'per_page': '100'})
        logging.info("All medias deleted")

    def delete_draft_pages(self):
        """
        Delete all pages in DRAFT status
        """
        cmd = "post list --post_type='page' --post_status=draft --format=csv --field=ID"
        pages_id_list = self.run_wp_cli(cmd)

        if not pages_id_list:
            for page_id in pages_id_list.split("\n")[1:]:
                cmd = "post delete {} --force".format(page_id)
                self.run_wp_cli(cmd)
            logging.info("All pages in DRAFT status deleted")

    def delete_pages(self):
        """
        Delete all pages in Wordpress via WP REST API
        HTTP delete /wp-json/wp/v2/pages/61?force=true
        """
        pages = self.wp.get_pages(params={'per_page': '100'})
        while len(pages) != 0:
            for page in pages:
                self.wp.delete_pages(page_id=page['id'], params={'force': 'true'})
            pages = self.wp.get_pages(params={'per_page': '100'})
        logging.info("All pages and menus deleted")

    def delete_widgets(self):
        """
        Delete all widgets in all existing sidebars
        """
        # List all sidebars
        cmd = "sidebar list --fields=id --format=csv"
        sidebar_id_list = self.run_wp_cli(cmd).split("\n")[1:]

        for sidebar_id in sidebar_id_list:
            cmd = "widget list {} --fields=id --format=csv".format(sidebar_id)
            widgets_id_list = self.run_wp_cli(cmd).split("\n")[1:]
            for widget_id in widgets_id_list:
                cmd = "widget delete {}".format(widget_id)
                self.run_wp_cli(cmd)
            if widgets_id_list:
                logging.info("Widgets deleted for sidebar '%s'", sidebar_id)
        logging.info("All widgets deleted")

    def delete_menu(self):
        """
        Delete all menus
        """
        cmd = "menu list --fields=term_id --format=csv"
        menus_id_list = self.run_wp_cli(cmd).split("\n")[1:]
        for menu_id in menus_id_list:
            cmd = "menu delete {}".format(menu_id)
            self.run_wp_cli(cmd)

    def display_report(self):
        """
        Display report
        """
        print("Imported in WordPress:\n"
              "- {files} files\n"
              "- {pages} pages\n"
              "- {menus} menus\n"
              "\n"
              "Errors:\n"
              "- {failed_files} files\n"
              "- {failed_menus} menus\n"
              "- {failed_widgets} widgets\n".format(**self.report))

    def write_redirections(self):
        """
        Update .htaccess file with redirections
        """
        redirect_list = []

        # Init WP install folder path for source URLs
        if self.wp_generator.wp_site.folder == "":
            folder = ""
        else:
            folder = "/{}".format(self.wp_generator.wp_site.folder)

        # Add all rewrite jahia URI to WordPress URI
        for element in self.urls_mapping:
            # WordPress URL is generated from slug so if admin change page location, it still will be available
            # if we request and "old" Jahia URL
            wp_url = "/{}/".format(element['wp_slug'])

            # Going through vanity URLs
            for jahia_url in element['jahia_urls']:

                # We skip this redirection to avoid infinite redirection...
                if jahia_url != "/index.html":
                    source_url = "{}{}".format(folder, jahia_url)
                    target_url = "{}{}".format(folder, wp_url)
                    # To avoid Infinite loop
                    if source_url != target_url[:-1]:
                        redirect_list.append("Redirect 301 {} {}".format(source_url,  target_url))

        if redirect_list:
            # Updating .htaccess file
            WPUtils.insert_in_htaccess(self.wp_generator.wp_site.path,
                                       "Jahia-Page-Redirect",
                                       redirect_list,
                                       at_beginning=True)
