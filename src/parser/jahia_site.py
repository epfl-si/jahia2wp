"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""

import os
import logging
import collections
import re
import settings
import base64

from bs4 import BeautifulSoup
from parser.box import Box
from parser.file import File
from parser.link import Link
from parser.page import Page
from parser.page_content import PageContent
from parser.sitemap_node import SitemapNode
from parser.menu_item import MenuItem
from parser.banner import Banner
from parser.box_sorted_group import BoxSortedGroup
from utils import Utils
from collections import OrderedDict
from datetime import datetime

"""
This file is named jahia_site to avoid a conflict with Site [https://docs.python.org/3/library/site.html]
"""


class Site:
    """A Jahia Site. Have 1 to N Pages"""

    def __init__(self, base_path, name, root_path="", fix_problematic_chars=False):
        """
        Create an instance of object

        :param base_path: Base path to dir containing extracted Jahia ZIP files
        :param name: Site name
        :param root_path: (optional) ?
        :param fix_problematic_chars: (optional) to tell if we have to fix problematic characters. Those includes :
                - x03 chars that may be in export_<lang>.xml files. If this character (=ETX -> End Of Text) is present
                  in a value read by DOM utils, the string is truncated at x03 position and the following characters
                  are ignored.
                - same problem with x0B chars... (vertical tabs)
        """
        # FIXME: base_path should not depend on output-dir
        self.base_path = base_path
        self.name = name
        # the server name, e.g. "master.epfl.ch"
        self.server_name = ""
        # the root_path, by default it's empty
        # FIXME: would be better in exporter, or set by exporter
        self.root_path = root_path

        # Type of links we have to ignore during link fix process
        self.link_type_to_ignore = ["javascript", "tel://", "tel:", "callto:", "smb://", "file://"]

        # parse the properties at the beginning, we need the
        # server_name for later
        self.parse_properties()

        # the export files containing the pages data.
        # the dict key is the language code (e.g. "en") and
        # the dict value is the file absolute path
        self.export_files = {}

        # To store root menu entries and submenu entries (entries pointing to pages and entries which are URL)
        # As key we have language and as value, a list with menu entries.
        self.menus = {}

        # the site languages
        self.languages = []

        # List of sort groups used to sort groups of boxes
        self.box_sort_groups = {}

        # the WordPress shortcodes used in the site. The key is the shortcode name,
        # and the value is the shortcode attributes containing URLs that must be
        # fixed by the WPExporter, e.g.:
        # {'epfl_snippets': ['url', 'image', 'big_image']}
        self.shortcodes = {}

        for file in os.listdir(self.base_path):
            if file.startswith("export_"):
                language = file[7:9]
                path = self.base_path + "/" + file
                self.export_files[language] = path
                self.languages.append(language)

        # If we have to fix problematic char in XML file,
        if fix_problematic_chars:
            self.fix_problematic_chars()

        # site params that are parsed later. There are dicts because
        # we have a value for each language. The dict key is the language,
        # and the dict value is the specific value
        self.title = {}
        self.acronym = {}
        self.theme = {}
        self.css_url = {}

        # breadcrumb
        self.breadcrumb_title = {}
        self.breadcrumb_url = {}

        # Banner (may stay empty if no custom banner defined)
        # Dict with language as key and Banner object as value
        self.banner = {}

        # footer
        self.footer = {}

        # the Pages indexed by their pid and uuid
        self.pages_by_pid = OrderedDict()
        self.pages_by_uuid = {}

        # the PageContents indexed by their path
        self.pages_content_by_path = {}

        # variables for the report
        self.num_files = 0
        self.num_pages = 0
        self.internal_links = 0
        self.absolute_links = 0
        self.external_links = 0
        self.file_links = 0
        self.data_links = 0
        self.mailto_links = 0
        self.anchor_links = 0
        self.broken_links = 0
        self.unknown_links = 0
        self.num_boxes = {}
        self.num_url_menu = 0
        self.num_link_menu = 0
        self.num_templates = {}
        # the number of each html tags, e.g. "br": 10
        self.num_tags = {}
        # we have a SitemapNode for each language
        self.sitemaps = {}
        self.report = ""

        # set for convenience, to avoid:
        #   [p for p in self.pages if p.is_homepage()][0]
        self.homepage = None

        # the files
        self.files = []

        # To map file UUID (used in URL in pages) to "real" URL because Jahia allows to override an URL by another
        # which is different from the file location on disk. So when we fix files URLs in pages during import we have
        # to use UUIDs and replace it with corresponding URL.
        self.file_uuid_to_url = {}

        # parse the data
        self.parse_data()

        # build the sitemaps
        self.build_sitemaps()

        # generate the report
        self.generate_report()

    def fix_problematic_chars(self):
        """
        Remove problematic chars (End of Text, vertical tabs) characters from XML files. If this character is present
        in a value read by DOM utils, the string is truncated at ETX position and the following characters are ignored.
        :return:
        """

        # Fixing all XML files
        for language, dom_path in self.export_files.items():
            # To rename original file before reading it to remove ETX chars.
            old_export_file = "{}.old".format(dom_path)
            # Remove if exists
            if os.path.exists(old_export_file):
                os.remove(old_export_file)
            os.rename(dom_path, old_export_file)

            in_file = open(old_export_file, 'rb')
            out_file = open(dom_path, 'wb')
            # Reading file content, replacing ETX char and Vertical Tabs and writing back to output file
            out_file.write(in_file.read().replace(b'\x03', b'').replace(b'\x0b', b''))

            in_file.close()
            out_file.close()
            # Remove temp file
            os.remove(old_export_file)

    def get_box_sort_group(self, uuid, sort_params):
        """
        Generate (if not exists) a Box sort group associated to given uuid.
        Sort handlers are managed here
        :param uuid: ID of sortHandler
        :param sort_params: String containing sort parameters
        :return: SortHandler object
        """

        if uuid not in self.box_sort_groups:

            # Sort handler for uuid doesn't exists so we have to create it
            # First, we extract params from sort_params: ex => "lastModified;desc;true;true"
            params = sort_params.split(";")

            self.box_sort_groups[uuid] = BoxSortedGroup(uuid, params[0], params[1])

        return self.box_sort_groups[uuid]

    def full_path(self, path):
        """
        FIXME : should be done in Exporter
        Prefix the given path with the site root_path
        """
        return self.root_path + path

    def parse_properties(self):
        """
        Parse the properties found in site.properties
        """

        properties = {}

        with open(self.base_path + "/site.properties") as file:
            lines = file.readlines()

            for line in lines:
                if "=" not in line:
                    continue

                values = line.split("=")

                properties[values[0].strip()] = values[1].strip()

        self.server_name = properties["siteservername"]

    def get_menu_entry_for_nav_page_uuid(self, nav_page_uuid):
        """
        Looks for menu entry which have the given nav_page_uuid
        :param nav_page_uuid: uuid to look for
        :return: List with :
                if found -> lang, MenuItem instance
                if not found -> None, None
        """
        for lang, menu_entries in self.menus.items():

            for menu_entry in menu_entries:
                result = menu_entry.find_nav_page_uuid(nav_page_uuid)

                if result:
                    return [lang, result]

        return [None, None]

    def parse_menu_entries(self, language, nav_list_list_node, parent_menu):
        """
        Parse menu entries, root and recursively sub entries of root.
        DOM menu structure is like
        <navigationListList>
            <navigationList>
                <navigationPage>
                    <jahia:page>                                    -> root menu entry (level 1)
                    <jahia:page>                                    -> root menu entry (level 1)
                        <navigationListList>
                            <navigationList>
                                <navigationPage>
                                    <jahia:page>                    -> Level 2 menu entry
                                        <navigationListList>
                                            <navigationList>
                                                <navigationPage>
                                                    <jahia:page>    -> Level 3 menu entry
                                    <jahia:page>
                                    ...
                    <jahia:page>                                    -> root menu entry (level 1)
                    ...

        :param language: Language for which the menu is parsed
        :param nav_list_list_node: DOM element of type 'navigationListList' from 'export_<lang>.xml' file
        :param parent_menu: None if first call to this function and MenuItem instance if recursive call
        :return:
        """
        nav_list_nodes = Utils.get_dom_next_level_children(nav_list_list_node, "navigationList")

        for nav_list in nav_list_nodes:

            nav_page_nodes = Utils.get_dom_next_level_children(nav_list, "navigationPage")

            for nav_page in nav_page_nodes:

                nav_page_uuid = nav_page.getAttribute("jcr:uuid")

                for jahia_type in nav_page.childNodes:

                    hidden = False
                    # If normal jahia page
                    if jahia_type.nodeName == "jahia:page":
                        txt = jahia_type.getAttribute("jahia:title")
                        # If title is empty, it means page is not displayed on Jahia, so we skip it here
                        if txt == '':
                            continue
                        hidden = jahia_type.getAttribute("jahia:hideFromNavigationMenu") != ""
                        points_to = "sitemap" if jahia_type.getAttribute("jahia:template") == "sitemap" \
                            else jahia_type.getAttribute("jcr:uuid")
                    # If URL
                    elif jahia_type.nodeName == "jahia:url":
                        txt = jahia_type.getAttribute("jahia:title")
                        hidden = jahia_type.getAttribute("jahia:hideFromNavigationMenu") != ""
                        points_to = jahia_type.getAttribute("jahia:value")

                        self.num_url_menu += 1
                    # If link to another page in the menu
                    elif jahia_type.nodeName == "jahia:link":
                        txt = jahia_type.getAttribute("jahia:title")
                        points_to = jahia_type.getAttribute("jahia:reference")

                        self.num_link_menu += 1
                    else:
                        continue

                    menu_item = MenuItem(txt, points_to, hidden, nav_page_uuid)

                    # If we are parsing root menu entries
                    if parent_menu is None:
                        self.menus[language].append(menu_item)

                    else:  # We are parsing sub-menu entries
                        parent_menu.children.append(menu_item)

                    # If there are sub-menu entries
                    nav_list_list_nodes = Utils.get_dom_next_level_children(jahia_type, "navigationListList")
                    if nav_list_list_nodes:
                        # Parsing sub menu entries
                        self.parse_menu_entries(language, nav_list_list_nodes[0], menu_item)

                # If no childNode, it means <navigationPage> can be a "reference" to a menu entry of another language
                if not nav_page.childNodes:
                    # Creating a menu entry with a blank title because we don't know the "target" title.
                    menu_item = MenuItem("", nav_page_uuid, False, None)

                    # If we are parsing root menu entries
                    if parent_menu is None:
                        self.menus[language].append(menu_item)

                    else:  # We are parsing sub-menu entries
                        parent_menu.children.append(menu_item)

        # Looking for sort information. If exists, the format is the following:
        # epfl_simple_navigationList_navigationPage;asc;false;false
        # Note: If sorting is done using 'txt' field (menu title), it will be incorrect if there are references to
        # menu entries in other language.
        sort_infos = nav_list_list_node.getAttribute("jahia:sortHandler")
        if sort_infos:
            # FIXME: For now, root menu entries sorting is not handled because never encountered in a Jahia Website
            # If we are parsing root sub-menu entries
            if parent_menu is not None:
                # Sorting children and store information about sort way
                parent_menu.sort_children(sort_infos.split(";")[1])

    def parse_menu(self):
        for language, dom_path in self.export_files.items():
            dom = Utils.get_dom(dom_path)

            self.menus[language] = []

            for nav_list_list in dom.getElementsByTagName("navigationListList"):

                # If list is right under 'root'
                if nav_list_list.parentNode.getAttribute("xmlns:jahia") != "":

                    self.parse_menu_entries(language, nav_list_list, None)

    def parse_banner(self):
        """ Extracting banner information if found """
        for language, dom_path in self.export_files.items():
            dom = Utils.get_dom(dom_path)

            banner_list_list = dom.getElementsByTagName("bannerListList")
            # If banner information is found
            if banner_list_list:
                # Adding banner for current lang
                self.banner[language] = Banner(Utils.get_tag_attribute(banner_list_list[0], "banner", "jahia:value"))

    def get_report_info(self):
        """
        Returns the report info as a dict.
        """

        # common info
        info = {
            "name": self.name,
            "pages": self.num_pages,
            "files": self.num_files,
        }

        # add the num_boxes
        info.update(self.num_boxes)

        return info

    def get_num_boxes(self, type):
        """Return the number of boxes for the given type"""
        if type in self.num_boxes:
            return self.num_boxes[type]
        else:
            return 0

    def parse_data(self):
        """Parse the Site data"""

        # do the parsing
        self.parse_site_params()
        self.parse_menu()
        self.parse_breadcrumb()
        self.parse_banner()
        self.parse_footer()
        self.parse_pages()
        self.parse_pages_content()
        self.parse_files()
        self.fix_links()

    def parse_site_params(self,):
        """Parse the site params"""
        for language, dom_path in self.export_files.items():
            dom = Utils.get_dom(dom_path)

            self.title[language] = Utils.get_tag_attribute(dom, "siteName", "jahia:value").strip()
            self.theme[language] = Utils.get_tag_attribute(dom, "theme", "jahia:value")
            if self.theme[language] == 'associations':
                self.theme[language] = 'assoc'
            if self.theme[language] == 'interfaculte':
                self.theme[language] = None
            self.acronym[language] = Utils.get_tag_attribute(dom, "acronym", "jahia:value")
            self.css_url[language] = "//static.epfl.ch/v0.23.0/styles/{}-built.css".format(self.theme[language])

    def parse_footer(self):
        """parse site footer"""

        for language, dom_path in self.export_files.items():
            dom = Utils.get_dom(dom_path)

            # is positioned on children of main jahia:page element
            elements = dom.firstChild.childNodes

            self.footer[language] = []

            for child in elements:
                if child.ELEMENT_NODE != child.nodeType:
                    continue

                if "bottomLinksListList" == child.nodeName:

                    nb_items_in_footer = len(child.getElementsByTagName("jahia:url"))

                    if nb_items_in_footer == 0:
                        """ This page has probably the default footer """
                        break

                    elif nb_items_in_footer > 0:

                        elements = child.getElementsByTagName("jahia:url")
                        for element in elements:
                            link = Link(
                                url=element.getAttribute('jahia:value'),
                                title=element.getAttribute('jahia:title')
                            )
                            self.footer[language].append(link)
                        break

    def parse_breadcrumb(self):
        """Parse the breadcrumb"""

        for language, dom_path in self.export_files.items():
            dom = Utils.get_dom(dom_path)
            self.breadcrumb_url[language] = []
            self.breadcrumb_title[language] = []

            breadcrumb_links = dom.getElementsByTagName("breadCrumbLink")
            if len(breadcrumb_links) == 0:
                continue

            for breadcrumb_link in breadcrumb_links:
                for child in breadcrumb_link.childNodes:
                    if child.ELEMENT_NODE != child.nodeType:
                        continue

                    if 'jahia:url' == child.nodeName:
                        self.breadcrumb_url[language].append(child.getAttribute('jahia:value'))
                        self.breadcrumb_title[language].append(child.getAttribute('jahia:title'))
                        break

    def parse_pages(self):
        """
        Parse the Pages. Here we parse only the common data between
        multilingual pages
        """

        # we check each export files because a Page could be defined
        # in one language but not in another
        for language, dom_path in self.export_files.items():
            dom = Utils.get_dom(dom_path)

            xml_pages = dom.getElementsByTagName("jahia:page")

            for xml_page in xml_pages:
                pid = xml_page.getAttribute("jahia:pid")
                template = xml_page.getAttribute("jahia:template")

                # we don't parse the sitemap as it's not a real page
                if template == "sitemap":
                    continue

                # check if we already parsed this page
                if pid in self.pages_by_pid:
                    continue

                page = Page(self, xml_page)

                # flag the homepage for convenience
                if page.is_homepage():
                    self.homepage = page

                # add the Page to the cache
                self.pages_by_pid[page.pid] = page
                self.pages_by_uuid[page.uuid] = page

    def parse_pages_content(self):
        """
        Parse the PageContent. This is the content that is specific
        for each language.
        """
        today = datetime.now().strftime("%Y-%m-%d")

        for language, dom_path in self.export_files.items():
            dom = Utils.get_dom(dom_path)

            xml_pages = dom.getElementsByTagName("jahia:page")

            for xml_page in xml_pages:
                pid = xml_page.getAttribute("jahia:pid")
                template = xml_page.getAttribute("jahia:template")

                # we don't parse the sitemap as it's not a real page
                if template == "sitemap":
                    continue

                # looking for end of validation date
                valid_to = xml_page.getAttribute("jahia:validTo")

                # If we have a validity date
                if valid_to:
                    # if format like "2017-10-06T11:03:00", we extract only the date.
                    if "T" in valid_to:
                        valid_to = valid_to.split("T")[0]

                    # if page is not valid anymore, we skip it
                    if valid_to < today:
                        continue

                # retrieve the Page definition that we already parsed
                page = self.pages_by_pid[pid]
                page_content = PageContent(page, language, xml_page)

                # the tags that can contain boxes. Sidebar boxes that are in <extra> tags
                # are parsed separately
                # Tags starting with "oneCol_*" are for special template like the one used for alice.epfl.ch
                tags = ["banner", "main", "col4", "col5" "col6", "col7", "col8", "oneCol_left_links",
                        "oneCol_down_links", "oneCol_up_links", "oneCol_right_links"]

                for tag in tags:
                    self.add_boxes(xml_page=xml_page,
                                   page_content=page_content,
                                   tag=tag)

                # count the tags
                self.count_tags(page_content)

                page.contents[language] = page_content

    def count_tags(self, page_content):
        """Count each html tags"""

        for box in page_content.boxes:
            soup = BeautifulSoup(box.content, 'html.parser')

            for tag in soup.find_all():
                # we increment both at the page_content and at the site level
                Utils.increment_count(page_content.num_tags, tag.name)
                Utils.increment_count(self.num_tags, tag.name)

    def add_boxes(self, xml_page, page_content, tag):
        # add the boxes contained in the given tag to the given page_content
        elements = xml_page.getElementsByTagName(tag)

        for element in elements:
            # check if the box belongs to the current page
            if not self.belongs_to(element, page_content.page):
                continue

            # TODO remove the multibox parameter and check for combo boxes instead
            # Check if xml_box contains many boxes
            multibox = element.getElementsByTagName("text").length > 1
            box = Box(site=self, page_content=page_content, element=element, multibox=multibox)
            page_content.boxes.append(box)

    def parse_files_uuids(self, node):
        """
        Parse node children to extract UUID and associated URL. If node has children, this method is recursively called
        for children.
        :param node: Node to parse
        :return:
        """
        node_names_to_ignore = ['jcr:content', 'thumbnail', 'thumbnail2']

        for file_node in node.childNodes:
            if file_node.ELEMENT_NODE != file_node.nodeType or \
                    file_node.nodeName in node_names_to_ignore:
                continue
            uuid = file_node.getAttribute("jcr:uuid")
            # If we're not on a file node (can
            if uuid == "":
                continue
            url = file_node.getAttribute("j:fullpath")
            # We remove the part :
            # /content/sites/<siteName>
            self.file_uuid_to_url[uuid] = url[url.index('/files/'):]

            # Recurse parsing
            if file_node.childNodes:
                self.parse_files_uuids(file_node)

    def parse_files(self):
        """Parse the files"""

        start = "{}/content/sites/{}/files".format(self.base_path, self.name)

        for (path, dirs, files) in os.walk(start):
            for file_name in files:
                # we exclude the thumbnails
                if file_name in ["thumbnail", "thumbnail2"]:
                    continue

                self.files.append(File(name=file_name, path=path))

        # Step 2 : looking for files "real" URLs (because can be overrided)
        repository_file = "{}/repository.xml".format(self.base_path)
        repository = Utils.get_dom(repository_file)

        files_node = repository.getElementsByTagName("files")[0]
        # Recursive parsing of files UUIDs
        self.parse_files_uuids(files_node)

    def register_shortcode(self, name, attributes, box):
        """
        Register the given shortcode with specified attributes. Content of those attributes (usually links) will be
        fixed during export in WordPress.

        :param name: the shortcode name
        :param attributes: a list with the shortcode attributes that must be fixed by WPExporter
        :param box: the Box where the shortcode was found
        """

        # save the attributes at the box level
        box.shortcode_attributes_to_fix = attributes

        # register the shortcode at the site level
        if name not in self.shortcodes:
            self.shortcodes[name] = attributes

    def get_all_boxes(self):
        """
        Returns all the Site boxes.
        """
        boxes = []

        for page in self.pages_by_pid.values():
            for page_content in page.contents.values():
                # boxes in the content
                for box in page_content.boxes:
                    boxes.append(box)
                # boxes in the sidebar
                for box in page_content.sidebar.boxes:
                    boxes.append(box)

        return boxes

    def belongs_to(self, element, page):
        """Check if the given element belongs to the given page"""
        parent = element.parentNode

        while "jahia:page" != parent.nodeName:
            parent = parent.parentNode

        return page.pid == parent.getAttribute("jahia:pid")

    def fix_links(self):
        """
        Fix all the boxes and banners links. This must be done at the end, when all the pages have been parsed.
        """

        # 1. Looping through Boxes
        for box in self.get_all_boxes():
            soup = BeautifulSoup(box.content, 'html5lib')
            soup.body.hidden = True

            for tag_name, tag_attribute in settings.FILE_LINKS_TAG_TO_FIX:
                self.fix_all_links_in_tag(box=box, soup=soup, tag_name=tag_name, attribute=tag_attribute)

        # 2. Looping through banners to fix only file links
        # FIXME: Maybe, in the future, we will have to also fix other types of links in banners
        for lang, banner in self.banner.items():

            soup = BeautifulSoup(banner.content, 'html5lib')
            soup.body.hidden = True

            for tag_name, tag_attribute in settings.FILE_LINKS_TAG_TO_FIX:
                self.fix_file_links_in_tag(soup=soup, tag_name=tag_name, attribute=tag_attribute)

            # save the new banner content
            banner.content = str(soup.body)

    def is_file_link(self, link):
        """
        Tells if given link is a pointing to a file
        :param link:
        :return:
        """
        return link.startswith("###file") or link.startswith('/repository') or \
            (link.startswith('../') and '/files/' in link)

    def fix_file_links_in_tag(self, soup, tag_name, attribute):
        """
        Fix only links to files in given BeautifulSoup object.
        This code was previously in fix_all_links_in_tag() function but because we also have to fix file links for
        banners and not only for boxes, it has been moved to a dedicated function.
        File links update will be directly done in 'soup' parameter and nothing will be returned by the function

        :param soup: instance of BeautifulSoup in which file links have to be fixed.
        :param tag_name: name of tag to look for
        :param attribute: name of tag attribute to update
        :return:
        """

        tags = soup.find_all(tag_name)

        for tag in tags:
            link = tag.get(attribute)

            if not link:
                continue

            skip_link = False
            for link_type in self.link_type_to_ignore:
                if link.startswith(link_type):
                    skip_link = True

            if skip_link:
                continue

            if self.is_file_link(link):

                if "/files/" in link:
                    new_link = link[link.rindex('/files/'):]

                    # If we have a link like this :
                    # ?uuid=default:a6d36162-07da-4036-9b58-a32e416f7769
                    if "?" in new_link and "?uuid=default:" in new_link:

                        uuid = new_link[new_link.index(":") + 1:]

                        # If we have an UUID match, we take it. Otherwise, we take the "real" link.
                        if uuid in self.file_uuid_to_url:
                            new_link = self.file_uuid_to_url[uuid]
                        else:
                            new_link = new_link[:new_link.index("?")]

                    else:  # We don't have an UUID in the link
                        if "?" in new_link:
                            new_link = new_link[:new_link.index("?")]

                    tag[attribute] = self.full_path(new_link)

                    self.file_links += 1

                # if we don't have /files/ in the path the link is broken (happen
                # only in 3 sites)
                else:
                    self.broken_links += 1
                    logging.debug("Found broken file link %s", link)

    def fix_all_links_in_tag(self, box, soup, tag_name, attribute):
        """
        Fix all types of links in given box for given tag and attribute.
        Updates will directly be done in 'box' parameter and nothing will be returned by function.

        :param box: instance of Box in which links have to be fixed
        :param soup: instance of BeautifulSoup with box content
        :param tag_name: name of tag to look for
        :param attribute: name of tag attribute to update
        """

        def add_lang_to_link(link, link_lang):
            """
            Add lang to given link if not part of it

            :param link: Link to check
            :param link_lang: Lang to add to link if not exists
            :return: link corrected
            """
            # If link doesn't contains lang => /page-92507.html
            # We transform it to => /page-92507-<defaultLang>.html
            reg = re.compile("/page-[0-9]+\.html")
            if reg.match(link):
                link_parts = link.split(".")
                # Adding default language to link
                link = "{}-{}.{}".format(link_parts[0], link_lang, link_parts[1])

            return link

        tags = soup.find_all(tag_name)

        # Regex to parse internal Jahia links. Here are type of links that can exists
        # /cms/op/edit/lang/fr/ref/6a37c1e5-b935-409b-8c51-b0fe8834aef1
        # http://jahia-prod.epfl.ch/cms/op/edit/lang/fr/ref/6a37c1e5-b935-409b-8c51-b0fe8834aef1
        # ###page:/lang/fr/ref/be42d070-0578-4278-89f3-f3f48e261814
        internal_link_reg = re.compile("(^###page|[\w:/.-]+/op/edit/(\w+/)+ref/[\w-]+)")

        for tag in tags:
            link = tag.get(attribute)

            if not link:
                continue

            for link_type in self.link_type_to_ignore:
                if link.startswith(link_type):
                    return

            # internal Jahia links
            if internal_link_reg.match(link):
                uuid = link[link.rfind('/') + 1:]

                # check if we have a Page with this uuid
                if uuid in self.pages_by_uuid:
                    page = self.pages_by_uuid[uuid]

                    # check if we have a PageContent with this language
                    if box.page_content.language in page.contents:
                        new_link = page.contents[box.page_content.language].path

                        tag[attribute] = new_link

                        self.internal_links += 1
                    else:
                        logging.debug("Found a broken link : " + link)
                        self.broken_links += 1
                else:
                    logging.debug("Found a broken link : " + link)
                    self.broken_links += 1

            # some weird internal links look like :
            # /cms/op/edit/PAGE_NAME or
            # /cms/site/SITE_NAME/op/edit/lang/LANGUAGE/PAGE_NAME
            elif "/op/edit/" in link:
                # To have /PAGE_NAME$
                # or /lang/LANGUAGE/PAGE_NAME
                new_link = link[link.index("/op/edit") + 8:]

                # if link like /lang/LANGUAGE/PAGE_NAME
                if new_link.startswith("/lang/"):
                    link_lang = new_link.split("/")[2]
                    # To have /PAGE_NAME
                    new_link = new_link[8:]

                else:  # Link like /PAGE_NAME
                    # Site has probably only one language so we take it to build new URL
                    link_lang = self.languages[0]

                tag[attribute] = add_lang_to_link(new_link, link_lang)

                self.internal_links += 1

            # /cms/PAGE_NAME
            elif link.startswith("/cms/"):
                new_link = link.replace("/cms", "")

                tag[attribute] = add_lang_to_link(new_link, self.languages[0])

            # Internal links like
            # /site/SITE_NAME/page-14009.html
            elif link.startswith("/site/{}".format(self.name)):
                new_link = link.replace("/site/{}".format(self.name), "")

                tag[attribute] = add_lang_to_link(new_link, self.languages[0])

            # internal links written by hand, e.g.
            # /team
            # /page-92507-fr.html
            # FIXME : will not work it root_path is set to a subdir
            elif link in self.pages_content_by_path:
                self.internal_links += 1
            # absolute links rewritten as relative links
            elif link.startswith("http://" + self.server_name) or \
                    link.startswith("https://" + self.server_name):
                # Link will be rebuild in exporter
                self.absolute_links += 1
            # file links
            elif self.is_file_link(link):

                self.fix_file_links_in_tag(soup, tag_name, attribute)

            # broken file links
            elif link.startswith("/fileNotFound###"):
                self.broken_links += 1
                logging.debug("Found broken file link %s", link)
            # those are files links we already fixed, so we pass
            elif link.startswith(self.root_path + "/files/"):
                pass
            # external links
            elif link.startswith("http://") or link.startswith("https://") or link.startswith("//"):
                self.external_links += 1
            # data links
            elif link.startswith("data:"):
                self.process_base64_image(tag, attribute, link)
                self.data_links += 1
            # mailto links
            elif link.startswith("mailto:"):
                self.mailto_links += 1
            # HTML anchors
            elif link.startswith("#"):
                self.anchor_links += 1
            else:
                logging.debug("Found unknown link %s", link)
                self.unknown_links += 1

        box.content = str(soup.body)

    def process_base64_image(self, tag, attribute, base64_string):
        """
        Create a image file with content of base64_string and update attribute value for given tag.

        :param tag: Tag to update
        :param attribute: attribute to update in tag
        :param base64_string: String containing image info, looks like :
         "data:image/png;base64,iVBORw0KGgoAAAANSUh....."
         "data:%3C;base64,iVBORw0KG....."
        :return:
        """
        # We extract image extension and string containing image
        ext_infos = base64_string.split(";")[0].split("/")

        # If image string is like "data:%3C;base64,iVBORw0KG" so we don't have extension information
        if len(ext_infos) == 1:
            # We assume it is PNG (because it is what has been seen during tests)
            ext = 'png'
        else:
            # We have a correct info about image extension
            ext = ext_infos[1]

        img_string = base64_string.split("base64,")[1]
        # Generating random filename
        filename = "{}.{}".format(Utils.get_random_string(20), ext)

        image_data = base64.b64decode(img_string)

        # We rebuild full path to create image at right place to be sure it will be uploaded with others medias
        file_directory = '{}/content/sites/{}/files/{}'.format(self.base_path, self.name, filename)
        file_path = "{}/{}".format(file_directory, filename)
        # Image directory creation if not exists
        if not os.path.exists(file_directory):
            os.makedirs(file_directory)

        with open(file_path, 'wb') as f:
            f.write(image_data)

        # Change base64 image data by link to created file. We have to take file directory instead of filename
        # because this is how it works
        tag[attribute] = file_directory[file_directory.rindex('/files/'):]

    def build_sitemaps(self):
        """Build the sitemaps"""

        for language in self.languages:
            # the root node (the homepage)
            root_node = SitemapNode(
                name=self.homepage.contents[language].title,
                ref=self.homepage.uuid,
                page=self.homepage)

            self._add_to_sitemap_node(root_node, language)

            self.sitemaps[language] = root_node

    def _add_to_sitemap_node(self, node, language):
        """Add the given SitemapNode. This is a recursive method"""

        # if we have more than 10 of depth there is an infinite loop
        # in the hierarchy, e.g. A > B > C > A > B > C > ...
        if node.depth > 10:
            logging.error("Sitemap is corrupted: infinite loop")
            return

        # for each NavigationPages...
        for navigation_page in node.page.contents[language].navigation:
            child_node = SitemapNode.from_navigation_page(navigation_page=navigation_page, parent=node)

            if not navigation_page.page:
                logging.warning("Sitemap is corrupted: navigation_page has no page associated")
                continue

            # if we have an internal NavigationPage, we add it's children
            if navigation_page.type == "internal" \
                    and language in navigation_page.page.contents \
                    and len(navigation_page.page.contents[language].navigation) > 0:

                # integrity check
                if child_node.page.pid == node.page.pid:
                    logging.warning("Sitemap is corrupted: parent and child are the same")
                    continue

                # recursive call
                self._add_to_sitemap_node(child_node, language)

    def print_sitemaps(self):
        """Print the sitemaps"""

        for language in self.languages:
            print("")
            print("─────────────────────────────────────────────────")
            print(" Sitemap for %s" % language)
            print("─────────────────────────────────────────────────")
            print("")

            node = self.sitemaps[language]

            node.print_node()

    def generate_report(self):
        """Generate the report of what has been parsed"""

        self.num_files = len(self.files)

        self.num_pages = len(self.pages_by_pid.values())

        # calculate the total number of boxes by type
        # dict key is the box type, dict value is the number of boxes

        for box in self.get_all_boxes():
            if box.type in self.num_boxes:
                self.num_boxes[box.type] = self.num_boxes[box.type] + 1
            else:
                self.num_boxes[box.type] = 1

        self.report = """
Parsed for %s :

  - %s files

  - %s pages :

""" % (self.server_name, self.num_files, self.num_pages)

        # order the dicts so they are always presented in the same order
        num_boxes_ordered = collections.OrderedDict(sorted(self.num_boxes.items()))
        num_templates_ordered = collections.OrderedDict(sorted(self.num_templates.items()))
        num_tags_ordered = sorted(self.num_tags, key=self.num_tags.get, reverse=True)

        # templates
        for key, value in num_templates_ordered.items():
            self.report += "    - %s using the template %s\n" % (value, key)

        # boxes
        for key, value in num_boxes_ordered.items():
            self.report += "    - %s %s boxes\n" % (value, key)

        # templates
        for key, value in num_templates_ordered.items():
            self.report += "    - %s using the template %s\n" % (value, key)

        # boxes
        for num, count in num_boxes_ordered.items():
            self.report += "    - %s %s boxes\n" % (count, num)

        self.report += "    - %s internal links\n" % self.internal_links
        self.report += "    - %s absolute links\n" % self.absolute_links
        self.report += "    - %s external links\n" % self.external_links
        self.report += "    - %s file links\n" % self.file_links
        self.report += "    - %s mailto links\n" % self.mailto_links
        self.report += "    - %s data links\n" % self.data_links
        self.report += "    - %s anchor links\n" % self.anchor_links
        self.report += "    - %s broken links\n" % self.broken_links
        self.report += "    - %s unknown links\n" % self.unknown_links
        self.report += "    - %s menu entries with URLs\n" % self.num_url_menu
        self.report += "    - %s duplicate menu entries to page\n" % self.num_link_menu

        # tags
        self.report += "\n"
        self.report += "  - tags :\n\n"

        for tag in num_tags_ordered:
            # Tag is encoded and decoded to remove special char that cause script to crash when report is printed if
            # it contains surprising tags !
            self.report += "    - <%s> %s\n" % (tag.encode('ascii', 'replace').decode('ascii').replace('?', ''),
                                                self.num_tags[tag])

    def __repr__(self):
        return self.name
