"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""

import os
import settings
import logging
import urllib
import requests

from utils import Utils


class JahiaPermissions:

    def __init__(self, site):
        self.jahia_permissions_files = []
        self.jahia_permissions_pages = []
        self.jahia_permissions_boxes = []
        self.site_path = os.path.join(settings.JAHIA_PERMISSIONS_PATH, site.name)

        if not os.path.isdir(self.site_path):
            os.mkdir(self.site_path)

    def write_jahia_permissions_files(self, csv_file_path):
        with open(csv_file_path, 'a', newline='\n') as csv:
            for file in self.jahia_permissions_files:
                csv.write("{}\n".format(file))
            csv.flush()

    def write_jahia_permissions_pages(self, csv_file_path):
        with open(csv_file_path, 'a', newline='\n') as csv:
            for page in self.jahia_permissions_pages:
                csv.write("{}\n".format(page))
            csv.flush()

    def write_jahia_permissions_boxes(self, csv_file_path):
        with open(csv_file_path, 'a', newline='\n') as csv:
            for box in self.jahia_permissions_boxes:
                csv.write("{},{},{}\n".format(box[0], box[1], box[2]))
            csv.flush()

    def get_server_name(self, site):
        server_name = site.server_name
        if server_name.startswith("http\\://"):
            server_name = "jahia-prod.epfl.ch"
        return server_name

    def build_jahia_page_url(self, site, element, language):
        """ Return the URL of jahia page """
        pid_page = element.getAttribute("jahia:pid")
        path = site.pages_by_pid[pid_page].contents[language].path
        if path == "/index.html":
            path = ""
        return "https://" + self.get_server_name(site) + path

    def build_jahia_file_url(self, site, node):

        file_path = node.getAttribute("j:fullpath")
        return "https://" + self.get_server_name(site) + "/files" + urllib.parse.quote(file_path)

    def is_leaf(self, node):
        """ Return True if node is a leaf of xml tree """

        for child in node.childNodes:
            if child.nodeName == "jcr:content":
                return True
        return False

    def find_leaves(self, site, node):
        """ Find all leaves of xml tree """

        if self.is_leaf(node):
            url = self.build_jahia_file_url(site, node)
            result = requests.head(url, allow_redirects=True)
            if result.status_code != 200:
                self.jahia_permissions_files.append(url)
        else:
            for child in node.childNodes:
                if child.ELEMENT_NODE != child.nodeType:
                    continue
                else:
                    self.find_leaves(site, child)

    def parse_files_permissions(self, site):
        """Parse the files and check if access is public"""

        # parse repository.xml file
        path = site.base_path + "/repository.xml"
        dom = Utils.get_dom(path)
        elements = dom.getElementsByTagName("files")
        node = elements[0]

        # all leaves of xml tree (repository.xml) are files used in Jahia
        self.find_leaves(site, node)

        # generate csv file
        jahia_permissions_files_path = os.path.join(self.site_path, "files.csv")
        self.write_jahia_permissions_files(jahia_permissions_files_path)

    def parse_box_permissions(self, site):
        """ Find all jahia boxes on which permissions are applied """
        logging.info("Box JAHIA with applied permissions")

        for language, dom_path in site.export_files.items():

            dom = Utils.get_dom(dom_path)
            elements = dom.getElementsByTagName("main")

            # For each jahia box check ACL
            for element in elements:
                if element.hasAttribute("jahia:acl"):
                    if element.getAttribute("jahia:acl") == 'none':
                        continue
                    else:

                        # Box type
                        box_type = ""
                        if element.hasAttribute("jcr:primaryType"):
                            box_type = element.getAttribute("jcr:primaryType")

                        # Box title
                        box_title = ""
                        for child in element.childNodes:
                            if child.nodeName == "boxTitle":
                                box_title = element.getAttribute("jahia:value")
                                break

                        # If ACL found, search jahia page parent
                        jahia_page_url = ""
                        while element.parentNode:
                            element = element.parentNode
                            if element.nodeName == "jahia:page":
                                jahia_page_url = self.build_jahia_page_url(site, element, language)
                                logging.debug(jahia_page_url)

                        self.jahia_permissions_boxes.append((jahia_page_url, box_title, box_type))

        jahia_permissions_boxes_path = os.path.join(self.site_path, "boxes.csv")
        self.write_jahia_permissions_boxes(jahia_permissions_boxes_path)

    def parse_page_permissions(self, site):
        """ Find all jahia boxes on which permissions are applied """
        logging.info("Jahia pages with applied permissions")
        for language, dom_path in site.export_files.items():

            dom = Utils.get_dom(dom_path)
            elements = dom.getElementsByTagName("jahia:page")

            # For each jahia pages check ACL
            for element in elements:

                if element.getAttribute("jahia:acl") == 'none':
                    continue
                else:
                    # If ACL found, build jahia page URL
                    jahia_page_url = self.build_jahia_page_url(site, element, language)
                    logging.debug(jahia_page_url)
                    self.jahia_permissions_pages.append(jahia_page_url)

        jahia_permissions_pages_path = os.path.join(self.site_path, "pages.csv")
        self.write_jahia_permissions_pages(jahia_permissions_pages_path)
