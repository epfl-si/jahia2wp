"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import logging
import os
import copy
import sys
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
from urllib.parse import urlparse

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

    def __init__(self, site, wp_generator, output_dir=None):
        """
        site is the python object resulting from the parsing of Jahia XML.
        site_host is the domain name.
        site_path is the url part of the site without the site_name.
        output_dir is the path where information files will be generated.
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

        # dictionary with the key 'wp_page_id' and the value 'wp_menu_id'
        self.menu_id_dict = {}
        self.output_dir = output_dir or settings.JAHIA_DATA_PATH
        self.wp_generator = wp_generator

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

    def import_all_data_to_wordpress(self):
        """
        Import all data to worpdress via REST API and wp-cli
        """
        try:
            start_time = timeit.default_timer()
            tracer_path = os.path.join(self.output_dir, self.TRACER_FILE_NAME)

            self.import_medias()
            self.import_pages()
            self.set_frontpage()
            self.populate_menu()
            self.import_sidebar()
            self.import_breadcrumb()
            self.delete_draft_pages()
            self.display_report()

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

    def import_medias(self):
        """
        Import medias to Wordpress
        """
        logging.info("WP medias import start")
        self.run_wp_cli('cap add administrator unfiltered_upload')
        for file in self.site.files:
            wp_media = self.import_media(file)
            if wp_media:
                self.fix_file_links(file, wp_media)
                self.report['files'] += 1
        # Remove the capability "unfiltered_upload" to the administrator group.
        self.run_wp_cli('cap remove administrator unfiltered_upload')
        logging.info("WP medias imported")

    def import_media(self, media):
        """
        Import a media to Wordpress
        """
        # Try to encode the path in ascii, if it fails then the path contains non-ascii characters.
        # In that case convert to ascii with 'replace' option which replaces unknown characters by '?',
        # and rename the file with that new name.
        try:
            media.path.encode('ascii')
        except UnicodeEncodeError:
            ascii_path = media.path.encode('ascii', 'replace').decode('ascii')
            os.rename(media.path, ascii_path)
            media.path = ascii_path
        try:
            media.name.encode('ascii')
        except UnicodeEncodeError:
            ascii_file_name = media.name.encode('ascii', 'replace').decode('ascii')
            os.rename(os.path.join(media.path, media.name), os.path.join(media.path, ascii_file_name))
            media.name = ascii_file_name
        file_path = os.path.join(media.path, media.name)
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
        # FIXME: add an attribut default_language to wp_generator.wp_site class
        default_lang = self.wp_generator._site_params['langs'].split(",")[0]

        # If there is a custom breadrcrumb defined for this site and the default language
        if self.site.breadcrumb_title and self.site.breadcrumb_url and \
                default_lang in self.site.breadcrumb_title and default_lang in self.site.breadcrumb_url:
            # Generatin breadcrumb to save in parameters
            breadcrumb = "[EPFL|www.epfl.ch]>[{}|{}]".format(self.site.breadcrumb_title[default_lang],
                                                             self.site.breadcrumb_url[default_lang])

            self.run_wp_cli("option update epfl:custom_breadcrumb '{}'".format(breadcrumb))

    def fix_file_links(self, file, wp_media):
        """Fix the links pointing to the given file"""

        if "/files" not in file.path:
            return

        # the old url is the file relative path
        old_url = file.path[file.path.rfind("/files"):]

        # the new url is the wp media source url
        new_url = wp_media['source_url']

        tag_attribute_tuples = [("a", "href"), ("img", "src"), ("script", "src")]

        for box in self.site.get_all_boxes():

            soup = BeautifulSoup(box.content, 'html.parser')

            for tag_name, tag_attribute in tag_attribute_tuples:

                self.fix_links_in_tag(
                    soup=soup,
                    old_url=old_url,
                    new_url=new_url,
                    tag_name=tag_name,
                    tag_attribute=tag_attribute)

            # save the new box content
            box.content = str(soup)

    def fix_page_content_links(self, wp_pages):
        """
        Fix all the links once we know all the WordPress pages urls
        """
        for wp_page in wp_pages:

            content = ""

            if "content" in wp_page:
                content = wp_page["content"]["raw"]
            else:
                logging.error("Expected content for page %s" % wp_page)

            soup = BeautifulSoup(content, 'html.parser')

            for url_mapping in self.urls_mapping:

                old_url = url_mapping["jahia_url"]
                new_url = url_mapping["wp_url"]

                self.fix_links_in_tag(
                    soup=soup,
                    old_url=old_url,
                    new_url=new_url,
                    tag_name="a",
                    tag_attribute="href"
                )

            # update the page
            wp_id = wp_page["id"]

            content = str(soup)

            self.update_page_content(page_id=wp_id, content=content)

    def fix_links_in_tag(self, soup, old_url, new_url, tag_name, tag_attribute):
        """Fix the links in the given tag"""

        tags = soup.find_all(tag_name)

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
            if link.encode('ascii', 'replace').decode('ascii').replace('?', '') == old_url.replace('?', ''):
                logging.debug("Changing link from %s to %s" % (old_url, new_url))
                tag[tag_attribute] = new_url

    def update_page(self, page_id, title, content):
        """
        Import a page to Wordpress
        """
        wp_page_info = {
            # date: auto => date/heure du jour
            # date_gmt: auto => date/heure du jour GMT
            # 'slug': slug,
            # 'status': 'publish',
            # password
            'title': title,
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
        return self.wp.post_pages(page_id=page_id, data=wp_page_info)

    def update_page_content(self, page_id, content):
        """Update the page content"""
        data = {"content": content}
        return self.wp.post_pages(page_id=page_id, data=data)

    def import_page(self, slug, title, content):

        wp_page_info = {
            # date: auto => date/heure du jour
            # date_gmt: auto => date/heure du jour GMT
            'slug': slug,
            'status': 'publish',
            # password
            'title': title,
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

        return self.wp.post_pages(data=wp_page_info)

    def import_pages(self):
        """
        Import all pages of jahia site to Wordpress
        """

        # keep the pages for fixing the links later
        wp_pages = []

        for page in self.site.pages_by_pid.values():

            contents = {}
            info_page = OrderedDict()

            for lang in page.contents.keys():
                contents[lang] = ""

                # create the page content
                for box in page.contents[lang].boxes:

                    contents[lang] += '<div class="{}">'.format(box.type + "Box")
                    if box.title:
                        contents[lang] += '<h3 id="{0}">{0}</h3>'.format(box.title)
                    contents[lang] += box.content
                    contents[lang] += "</div>"

                info_page[lang] = {
                    'post_name': page.contents[lang].path,
                    'post_status': 'publish',
                }

            # If the page doesn't exist for all languages on the site we create a blank page in draft status
            # At the end of export we delete all draft pages
            for lang in self.wp_generator._site_params['langs'].split(","):
                if lang not in info_page:
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
                continue

            wp_ids = result.split()

            if len(wp_ids) != len(contents):
                error_msg = "{} page created is not expected : {}".format(len(wp_ids), len(contents))
                logging.error(error_msg)
                continue

            for wp_id, (lang, content) in zip(wp_ids, contents.items()):
                wp_page = self.update_page(page_id=wp_id, title=page.contents[lang].title, content=content)

                # prepare mapping for the nginx conf generation
                mapping = {
                    'jahia_url': page.contents[lang].path,
                    'wp_url': wp_page['link']
                }

                self.urls_mapping.append(mapping)

                logging.info("WP page '%s' created", wp_page['link'])

                # keep WordPress ID for further usages
                page.contents[lang].wp_id = wp_page['id']

                wp_pages.append(wp_page)

            self.report['pages'] += 1

        self.fix_page_content_links(wp_pages)

        self.create_sitemaps()

        self.update_parent_ids()

    def update_parent_ids(self):
        """
        Update all pages to define the pages hierarchy
        """
        for page in self.site.pages_by_pid.values():
            for lang, page_content in page.contents.items():

                if page.parent and page_content.wp_id:
                    parent_id = page.parent.contents[lang].wp_id
                    wp_page_info = {
                        'parent': parent_id
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
                content='[simple-sitemap show_label="false" types="page orderby="menu_order"]'
            )
            self.create_footer_menu_for_sitemap(sitemap_wp_id=wp_page['id'], lang=lang)

    def import_sidebar(self):
        """
        import sidebar via wpcli
        """
        def prepare_html(html):
            return Utils.escape_quotes(html.replace(u'\xa0', u' '))

        widget_pos = 1
        widget_pos_to_lang = {}

        try:
            for lang in self.site.homepage.contents.keys():

                for box in self.site.homepage.contents[lang].sidebar.boxes:
                    if box.type == Box.TYPE_TEXT or box.type == Box.TYPE_CONTACT:
                        widget_type = 'text'
                        title = prepare_html(box.title)
                        content = prepare_html(box.content)

                    elif box.type == Box.TYPE_COLORED_TEXT:
                        widget_type = 'text'
                        title = ""
                        content = "[colored-box]"
                        content += "<h3>{}</h3>".format(box.title)
                        content += prepare_html(box.content)
                        content += "[/colored-box]"

                    # Box type not supported for now,
                    else:
                        logging.warning("Box type currently not supported for sidebar (%s)", box.type)
                        widget_type = 'text'
                        title = prepare_html("TODO: {}".format(box.title))
                        content = prepare_html(box.content)

                    cmd = 'widget add {} page-widgets {} ' \
                          '--text="{}" --title="{}"'.format(widget_type, widget_pos, content, title)

                    self.run_wp_cli(cmd)

                    # Saving widget position for current widget (as string because this is a string that is
                    # used to index informations in DB)
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

    def create_submenu(self, children, lang, menu_name):
        """
        Create recursively submenus for one main menu entry

        children - children pages of main menu entry
        lang - language
        menu_name - name of WP menu where to put sub-menu entries
        """
        for child in children:

            if lang in child.contents and child.parent.contents[lang].wp_id in self.menu_id_dict and \
                    child.contents[lang].wp_id:  # FIXME For unknown reason, wp_id is sometimes None

                parent_menu_id = self.menu_id_dict[child.parent.contents[lang].wp_id]

                command = 'menu item add-post {} {} --parent-id={} --porcelain' \
                    .format(menu_name, child.contents[lang].wp_id, parent_menu_id)
                menu_id = self.run_wp_cli(command)
                if not menu_id:
                    logging.warning("Menu not created for page %s" % child.pid)
                else:
                    self.menu_id_dict[child.contents[lang].wp_id] = Utils.get_menu_id(menu_id)
                    self.report['menus'] += 1

            # FIXME: Handle sub-sub-pages entries
            self.create_submenu(child.children, lang, menu_name)

    def populate_menu(self):
        """
        Add pages into the menu in wordpress.
        This menu was created when configuring the polylang plugin.
        """
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
                        logging.warning("Home root menu not created for page  %s" % page_content.pid)
                    else:
                        self.menu_id_dict[page_content.wp_id] = Utils.get_menu_id(menu_id)
                        self.report['menus'] += 1

                # We do a copy of the list because we will use "pop()" later and empty the list
                children_copy = copy.deepcopy(self.site.homepage.children)

                # Looping through root menu entries
                for root_entry_index in range(0, self.site.menus[lang].nb_main_entries()):

                    # If root menu entry is an hardcoded URL
                    if self.site.menus[lang].target_is_url(root_entry_index):
                        cmd = 'menu item add-custom {} "{}" "{}" --porcelain' \
                            .format(menu_name,
                                    self.site.menus[lang].txt(root_entry_index),
                                    self.site.menus[lang].target_url(root_entry_index))
                        menu_id = self.run_wp_cli(cmd)
                        if not menu_id:
                            logging.warning("Root menu item not created for URL (%s) " %
                                            self.site.menus[lang].target_url())

                    # root menu entry is page
                    else:
                        homepage_child = children_copy.pop(0)

                        if lang not in homepage_child.contents:
                            logging.warning("Page not translated %s" % homepage_child.pid)
                            continue

                        if homepage_child.contents[lang].wp_id:
                            cmd = 'menu item add-post {} {} --porcelain' \
                                  .format(menu_name, homepage_child.contents[lang].wp_id)
                            menu_id = self.run_wp_cli(cmd)
                            if not menu_id:
                                logging.warning("Root menu item not created %s for page " % homepage_child.pid)
                            else:
                                self.menu_id_dict[homepage_child.contents[lang].wp_id] = Utils.get_menu_id(menu_id)
                                self.report['menus'] += 1

                        # create recursively submenus
                        self.create_submenu(homepage_child.children, lang, menu_name)

                logging.info("WP menus populated for '%s' language", lang)

        except Exception as e:
            logging.error("%s - WP export - menu failed: %s", self.site.name, e)
            self.report['failed_menus'] += 1
            raise e

    def set_frontpage(self):
        """
        Use wp-cli to set the two worpress options needed fotr the job
        """
        # sanity check on homepage
        if not self.site.homepage:
            raise Exception("No homepage defined for site")

        # call wp-cli
        self.run_wp_cli('option update show_on_front page')

        for lang in self.site.homepage.contents.keys():
            frontpage_id = self.site.homepage.contents[lang].wp_id
            result = self.run_wp_cli('option update page_on_front {}'.format(frontpage_id))
            if result is not None:
                # Set on only one language is sufficient
                logging.info("WP frontpage setted")
                break

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
        cmd = "post list --post_type='page' --post_status=draft --format=csv"
        pages_id_list = self.run_wp_cli(cmd).split("\n")[1:]
        for page_id in pages_id_list:
            cmd = "post delete {}".format(page_id)
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
        Delete all widgets
        """
        cmd = "widget list page-widgets --fields=id --format=csv"
        widgets_id_list = self.run_wp_cli(cmd).split("\n")[1:]
        for widget_id in widgets_id_list:
            cmd = "widget delete {}".format(widget_id)
            self.run_wp_cli(cmd)
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
        content = []

        # Add all rewrite jahia URI to WordPress URI
        for element in self.urls_mapping:
            # We skip this redirection to avoid infinite redirection...
            if element['jahia_url'] != "/index.html":

                content.append("Redirect 301 {} {}".format(element['jahia_url'][1:],
                                                           urlparse(element['wp_url']).path))

        if content:
            # Updating .htaccess file
            WPUtils.insert_in_htaccess(self.wp_generator.wp_site.path,
                                       "Jahia-Page-Redirect",
                                       content,
                                       at_beginning=True)