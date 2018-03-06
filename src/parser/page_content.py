"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
from datetime import datetime

from parser.box import Box
from parser.navigation_page import NavigationPage
from settings import JAHIA_DATE_FORMAT
from parser.sidebar import Sidebar
import logging
import re


class PageContent:
    """
    The language specific data of a Page
    """
    def __init__(self, page, language, element):
        self.element = element
        self.page = page
        self.site = page.site
        self.wp_id = None
        self.language = language
        # the relative path, e.g. /team.html
        self.path = ""
        self.vanity_urls = []
        self.title = element.getAttribute("jahia:title")
        self.boxes = []
        self.sidebar = Sidebar()
        self.last_update = ""
        # a list of NavigationPages
        self.navigation = []

        # last update
        self.parse_last_update()

        # sidebar
        self.parse_sidebar()

        # path
        self.set_path()

        # navigation
        self.parse_navigation()

        # add to the site PageContents
        self.site.pages_content_by_path[self.path] = self

    def parse_last_update(self):
        """Parse the last update information"""
        date = self.element.getAttribute("jcr:lastModified")

        try:
            self.last_update = datetime.strptime(date, JAHIA_DATE_FORMAT)
        except ValueError as e:
            logging.error(
                "%s - parse - Invalid last update date for page %s : '%s'",
                self.site.name, self.page.pid, date)
            raise e

    def parse_sidebar(self):
        """ Parse sidebar """

        # search the sidebar in the page xml content
        children = self.element.childNodes
        for child in children:
            if child.nodeName == "extraList":
                for extra in child.childNodes:
                    if extra.ELEMENT_NODE != extra.nodeType:
                        continue
                    box = Box(site=self.site, page_content=self, element=extra)
                    self.sidebar.boxes.append(box)

        nb_boxes = len(self.sidebar.boxes)

        # if we don't have boxes in this sidebar we check the parents
        if nb_boxes == 0:
            parent = self.page.parent

            while parent:
                sidebar = parent.contents[self.language].sidebar

                # we found a sidebar with boxes, we stop
                if len(sidebar.boxes) > 0:
                    self.sidebar = sidebar
                    break

                # otherwise we continue in the hierarchy
                parent = parent.parent

    def set_path(self):
        """
        Set the page path
        """

        if self.page.is_homepage():
            if "en" == self.language:
                self.vanity_urls = ["/index.html"]
            else:
                self.vanity_urls = ["/index-{}.html".format(self.language)]
        else:
            # Vanity URL can have the following content :
            # one URL ==> '/sciences_donnees$$$true$$$true==='
            # many URLs ==> '/sciences_donnees$$$true$$$true===/sciencesdonnees$$$true$$$false==='
            # many URLs ==> '/sciences_donnees$$$true$$$false===/sciencesdonnees$$$true$$$false==='
            vanity_url = self.element.getAttribute("jahia:urlMappings")
            if vanity_url:
                # Going through exploded parts
                for url in vanity_url.split('$$$'):
                    # Cleaning content
                    url = re.sub(r'(true|false)(===)?', '', url)
                    if url:
                        self.vanity_urls.append(url)
            else:
                # use the old Jahia page id
                self.vanity_urls = ["/page-{}-{}.html".format(self.page.pid, self.language)]

        # FIXME, the prefixing part should be done in exporter
        # add the site root_path at the beginning
        self.path = self.site.root_path + self.vanity_urls[0]

    def parse_navigation(self):
        """Parse the navigation"""

        navigation_pages = self.element.getElementsByTagName("navigationPage")

        for navigation_page in navigation_pages:
            # check if the <navigationPage> belongs to this page
            if not self.site.belongs_to(element=navigation_page, page=self.page):
                continue

            for child in navigation_page.childNodes:
                # internal page declared with <jahia:page>
                if child.nodeName == "jahia:page":
                    template = child.getAttribute("jahia:template")

                    # we don't want the sitemap
                    if not template == "sitemap":
                        ref = child.getAttribute("jcr:uuid")
                        title = child.getAttribute("jahia:title")

                        self.add_navigation_page(type="internal", ref=ref, title=title)

                # internal page declared with <jahia:link>
                elif child.nodeName == "jahia:link":
                    ref = child.getAttribute("jahia:reference")
                    title = child.getAttribute("jahia:title")

                    self.add_navigation_page(type="internal", ref=ref, title=title)

                # external page
                elif child.nodeName == "jahia:url":
                    ref = child.getAttribute("jahia:value")
                    title = child.getAttribute("jahia:title")

                    self.add_navigation_page(type="external", ref=ref, title=title)

    def add_navigation_page(self, type, ref, title):
        """Add a NavigationPage with the given info"""

        navigation_page = NavigationPage(parent=self, type=type, ref=ref, title=title)

        self.navigation.append(navigation_page)
