"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import logging
import os
import sys
import timeit
from collections import OrderedDict
from datetime import timedelta, datetime

import simplejson
from bs4 import BeautifulSoup
from wordpress_json import WordpressJsonWrapper, WordpressError

import settings
from exporter.utils import Utils


class WPExporter:

    # this file is used to save data for importing data
    TRACER_FILE_NAME = "tracer_importing.csv"

    # list of mapping Jahia url and Wordpress url
    urls_mapping = []

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
        rest_api_url = "http://{}:8080/{}/?rest_route=/wp/v2".format(self.host, self.site.name)
        logging.info("setting up API on '%s', with %s:xxxxxx", rest_api_url, wp_generator.wp_admin.username)
        self.wp = WordpressJsonWrapper(rest_api_url, wp_generator.wp_admin.username, wp_generator.wp_admin.password)

    def run_wp_cli(self, command, encoding=sys.stdout.encoding, pipe_input=None, extra_options=None):
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

    def import_medias(self):
        """
        Import medias to Wordpress
        """
        logging.info("WP medias import start")
        for file in self.site.files:
            wp_media = self.import_media(file)
            if wp_media:
                self.fix_file_links(file, wp_media)
                self.report['files'] += 1
        logging.info("WP medias imported")

    def import_media(self, media):
        """
        Import a media to Wordpress
        """
        file_path = media.path + '/' + media.name
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

            if link == old_url:
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

            cmd = "pll post create --post_type=page --stdin --porcelain"
            stdin = simplejson.dumps(info_page)

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
        stdin = simplejson.dumps(info_page)
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
        try:
            for lang in self.site.homepage.contents.keys():
                for box in self.site.homepage.contents[lang].sidebar.boxes:
                    content = "<div class='box box-flat-panel home-navpanel local-color coloredTextBox'>"
                    content += Utils.escape_quotes(box.content)
                    content += "</div>"
                    cmd = 'widget add black-studio-tinymce page-widgets ' \
                          '--title="{}" --text="{}"'.format(box.title, content)

                    self.run_wp_cli(cmd)

                # Import sidebar for one language only
                break

            logging.info("WP all sidebar imported")

        except WordpressError as e:
            logging.error("%s - WP export - widget failed: %s", self.site.name, e)
            self.report['failed_widgets'] += 1

    def create_footer_menu_for_sitemap(self, sitemap_wp_id, lang):
        """
        Create footer menu for sitemap page
        """
        # FIXME: add an attribut default_language to wp_generator.wp_site class
        default_language = self.wp_generator._site_params['langs'].split(",")[0]
        if default_language == lang:
            footer_name = settings.FOOTER_MENU
        else:
            footer_name = "{}-{}".format(settings.FOOTER_MENU, default_language)

        self.run_wp_cli('menu item add-post {} {} --porcelain'.format(footer_name, sitemap_wp_id))

        # Create footer menu
        cmd = "menu item add-custom {} Accessibility http://www.epfl.ch/accessibility.en.shtml​".format(footer_name)
        self.run_wp_cli(cmd)

        # legal notice
        cmd = "menu item add-custom {} 'Legal Notice' http://mediacom.epfl.ch/disclaimer-en​".format(footer_name)
        self.run_wp_cli(cmd)

        # Report
        self.report['menus'] += 2

    def create_submenu(self, page, lang, menu_name):
        """
        Create recursively submenus.
        """
        if page not in self.site.homepage.children \
                and lang in page.contents \
                and page.parent.contents[lang].wp_id in self.menu_id_dict:

            parent_menu_id = self.menu_id_dict[page.parent.contents[lang].wp_id]

            command = 'menu item add-post {} {} --parent-id={} --porcelain' \
                      .format(menu_name, page.contents[lang].wp_id, parent_menu_id)
            menu_id = self.run_wp_cli(command)
            if not menu_id:
                logging.warning("Menu not created for page %s" % page.pid)
            else:
                self.menu_id_dict[page.contents[lang].wp_id] = Utils.get_menu_id(menu_id)
                self.report['menus'] += 1

        if page.has_children():
            for child in page.children:
                self.create_submenu(child, lang, menu_name)

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
                    menu_name = "{}-{}".format(settings.MAIN_MENU, default_language)

                cmd = 'menu item add-post {} {} --classes=link-home --porcelain'.format(menu_name, page_content.wp_id)
                menu_id = self.run_wp_cli(cmd)
                if not menu_id:
                    logging.warning("Menu not created for page  %s" % page_content.pid)
                else:
                    self.menu_id_dict[page_content.wp_id] = Utils.get_menu_id(menu_id)
                    self.report['menus'] += 1

                # Create children of homepage menu
                for homepage_child in self.site.homepage.children:

                    if lang not in homepage_child.contents:
                        logging.warning("Page not translated %s" % homepage_child.pid)
                        continue

                    if homepage_child.contents[lang].wp_id:
                        cmd = 'menu item add-post {} {} --porcelain' \
                              .format(menu_name, homepage_child.contents[lang].wp_id)
                        menu_id = self.run_wp_cli(cmd)
                        if not menu_id:
                            logging.warning("Menu not created %s for page " % homepage_child.pid)
                        else:
                            self.menu_id_dict[homepage_child.contents[lang].wp_id] = Utils.get_menu_id(menu_id)
                            self.report['menus'] += 1

                    # create recursively submenus
                    self.create_submenu(homepage_child, lang, menu_name)

                logging.info("WP menus populated")

        except Exception as e:
            logging.error("%s - WP export - menu failed: %s", self.site.name, e)
            self.report['failed_menus'] += 1

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
        cmd = "wp menu list --fields=term_id --format=csv"
        menus_id_list = self.run_wp_cli(cmd).split("\n")[1:]
        for menu_id in menus_id_list:
            cmd = "wp menu delete {}".format(menu_id)
            self.run_wp_cli(cmd)

    def display_report(self):
        """
        Display report
        """
        print("Imported in WordPress:\n"
              "- {files}s files\n"
              "- {pages}s pages\n"
              "- {menus}s menus\n"
              "\n"
              "Errors:\n"
              "- {failed_files}s files\n"
              "- {failed_menus}s menus\n"
              "- {failed_widgets}s widgets\n".format(**self.report))
