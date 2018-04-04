"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""

import logging
from urllib.parse import urlencode
from xml.dom import minidom

from utils import Utils


class Box:
    """A Jahia Box. Can be of type text, infoscience, etc."""

    # WP box types
    TYPE_TEXT = "text"
    TYPE_COLORED_TEXT = "coloredText"
    TYPE_PEOPLE_LIST = "peopleList"
    TYPE_INFOSCIENCE = "infoscience"
    TYPE_ACTU = "actu"
    TYPE_MEMENTO = "memento"
    TYPE_FAQ = "faq"
    TYPE_TOGGLE = "toggle"
    TYPE_INCLUDE = "include"
    TYPE_CONTACT = "contact"
    TYPE_XML = "xml"
    TYPE_LINKS = "links"
    TYPE_RSS = "rss"
    TYPE_FILES = "files"
    TYPE_SYNTAX_HIGHLIGHT = "syntaxHighlight"
    TYPE_KEY_VISUAL = "keyVisual"
    TYPE_MAP = "map"

    # Mapping of known box types from Jahia to WP
    types = {
        "epfl:textBox": TYPE_TEXT,
        "epfl:coloredTextBox": TYPE_COLORED_TEXT,
        "epfl:peopleListBox": TYPE_PEOPLE_LIST,
        "epfl:infoscienceBox": TYPE_INFOSCIENCE,
        "epfl:actuBox": TYPE_ACTU,
        "epfl:mementoBox": TYPE_MEMENTO,
        "epfl:faqContainer": TYPE_FAQ,
        "epfl:toggleBox": TYPE_TOGGLE,
        "epfl:htmlBox": TYPE_INCLUDE,
        "epfl:contactBox": TYPE_CONTACT,
        "epfl:xmlBox": TYPE_XML,
        "epfl:linksBox": TYPE_LINKS,
        "epfl:rssBox": TYPE_RSS,
        "epfl:filesBox": TYPE_FILES,
        "epfl:syntaxHighlightBox": TYPE_SYNTAX_HIGHLIGHT,
        "epfl:keyVisualBox": TYPE_KEY_VISUAL,
        "epfl:mapBox": TYPE_MAP
    }

    UPDATE_LANG = "UPDATE_LANG_BY_EXPORTER"

    def __init__(self, site, page_content, element, multibox=False):
        self.site = site
        self.page_content = page_content
        self.type = ""
        self.set_type(element)
        self.title = Utils.get_tag_attribute(element, "boxTitle", "jahia:value")
        self.content = ""
        self.set_content(element, multibox)

    def set_type(self, element):
        """
        Sets the box type
        """

        type = element.getAttribute("jcr:primaryType")

        if type in self.types:
            self.type = self.types[type]
        else:
            self.type = type

    def set_content(self, element, multibox=False):
        """set the box attributes"""

        # text
        if self.TYPE_TEXT == self.type or self.TYPE_COLORED_TEXT == self.type:
            self.set_box_text(element, multibox)
        # people list
        elif self.TYPE_PEOPLE_LIST == self.type:
            self.set_box_people_list(element)
        # infoscience
        elif self.TYPE_INFOSCIENCE == self.type:
            self.set_box_infoscience(element)
        # actu
        elif self.TYPE_ACTU == self.type:
            self.set_box_actu(element)
        # memento
        elif self.TYPE_MEMENTO == self.type:
            self.set_box_memento(element)
        # faq
        elif self.TYPE_FAQ == self.type:
            self.set_box_faq(element)
        # toggle
        elif self.TYPE_TOGGLE == self.type:
            self.set_box_toggle(element)
        # include
        elif self.TYPE_INCLUDE == self.type:
            self.set_box_include(element)
        # contact
        elif self.TYPE_CONTACT == self.type:
            self.set_box_contact(element)
        # xml
        elif self.TYPE_XML == self.type:
            self.set_box_xml(element)
        # links
        elif self.TYPE_LINKS == self.type:
            self.set_box_links(element)
        # rss
        elif self.TYPE_RSS == self.type:
            self.set_box_rss(element)
        # files
        elif self.TYPE_FILES == self.type:
            self.set_box_files(element)
        # syntaxHighlight
        elif self.TYPE_SYNTAX_HIGHLIGHT == self.type:
            self.set_box_syntax_highlight(element)
        # keyVisual
        elif self.TYPE_KEY_VISUAL == self.type:
            self.set_box_key_visuals(element)
        elif self.TYPE_MAP == self.type:
            self.set_box_map(element)
        # unknown
        else:
            self.set_box_unknown(element)

    def _set_scheduler_box(self, element, content):
        """set the attributes of a scheduler box"""

        start_datetime = Utils.get_tag_attribute(element, "comboList", "jahia:validFrom")

        if start_datetime and "T" in start_datetime:
            start_date = start_datetime.split("T")[0]
            start_time = start_datetime.split("T")[1]

        end_datetime = Utils.get_tag_attribute(element, "comboList", "jahia:validTo")

        if end_datetime and "T" in end_datetime:
            end_date = end_datetime.split("T")[0]
            end_time = end_datetime.split("T")[1]

        if not end_datetime and not start_datetime:
            logging.warning("Scheduler shortcode has no startdate and no enddate")

        return '[epfl_scheduler start_date="{}" end_date="{}" start_time="{}" end_time="{}"]{}[/epfl_scheduler]'.format(
            start_date,
            end_date,
            start_time,
            end_time,
            content
        )

    def set_box_text(self, element, multibox=False):
        """set the attributes of a text box
            A text box can have two forms, either it contains just a <text> tag
            or it contains a <comboListList> which contains <comboList> tags which
            contain <text>, <filesList>, <linksList> tags. The last two tags may miss from time
            to time because the jahia export is not perfect.
            FIXME: For now <filesList> are ignored because we did not find a site where
            it is used yet.
        """

        if not multibox:
            content = Utils.get_tag_attribute(element, "text", "jahia:value")
            linksList = element.getElementsByTagName("linksList")
            if linksList:
                content += self._parse_links_to_list(linksList[0])
        else:
            # copy textBox reference
            element_box_text = element

            # Concatenate HTML content of many boxes
            content = ""
            comboLists = element.getElementsByTagName("comboList")
            for element in comboLists:
                content += Utils.get_tag_attribute(element, "text", "jahia:value")
                # linksList contain <link> tags exactly like linksBox, so we can just reuse
                # the same code used to parse linksBox.
                content += self._parse_links_to_list(element)

            # scheduler shortcode
            if Utils.get_tag_attribute(element_box_text, "comboList", "jahia:ruleType") == "START_AND_END_DATE":
                content = self._set_scheduler_box(element_box_text, content)

        self.content = content

    def set_box_people_list(self, element):
        """
        Set the attributes of a people list box

        More information here:
        https://c4science.ch/source/kis-jahia6-dev/browse/master/core/src/main/webapp/common/box/display/peopleListBoxDisplay.jsp
        """
        BASE_URL = "https://people.epfl.ch/cgi-bin/getProfiles?"

        # prepare a dictionary with all GET parameters
        parameters = {}

        # parse the unit parameter
        parameters['unit'] = Utils.get_tag_attribute(element, "query", "jahia:value")

        # parse the template html
        template_html = Utils.get_tag_attribute(element, "template", "jahia:value")

        # extract template key
        template_key = Utils.get_tag_attribute(
            minidom.parseString(template_html),
            "jahia-resource",
            "key"
        )

        # these rules are extracted from jsp of jahia
        if template_key == 'epfl_peopleListContainer.template.default_bloc':
            parameters['struct'] = 1
            template = 'default_struct_bloc'
        elif template_key == 'epfl_peopleListContainer.template.default_bloc_simple':
            template = 'default_bloc'
        elif template_key == 'epfl_peopleListContainer.template.default_list':
            template = 'default_list'
        else:
            template = Utils.get_tag_attribute(minidom.parseString(template_html), "jahia-resource", "key")
        parameters['WP_tmpl'] = template

        # in the parser we can't know the current language.
        # so we assign a string that we will replace by the current language in the exporter
        parameters['lang'] = self.UPDATE_LANG

        url = "{}{}".format(BASE_URL, urlencode(parameters))
        self.content = '[epfl_people url="{}" /]'.format(url)

    def set_box_actu(self, element):
        """set the attributes of an actu box"""
        url = Utils.get_tag_attribute(element, "url", "jahia:value")

        self.content = "[actu url=%s]" % url

    def set_box_memento(self, element):
        """set the attributes of a memento box"""
        url = Utils.get_tag_attribute(element, "url", "jahia:value")

        self.content = "[memento url=%s]" % url

    def set_box_infoscience(self, element):
        """set the attributes of a infoscience box"""
        url = Utils.get_tag_attribute(element, "url", "jahia:value")

        self.content = "[epfl_infoscience url=%s]" % url

    def set_box_faq(self, element):
        """set the attributes of a faq box"""
        self.question = Utils.get_tag_attribute(element, "question", "jahia:value")

        self.answer = Utils.get_tag_attribute(element, "answer", "jahia:value")

        self.content = "<h2>%s</h2><p>%s</p>" % (self.question, self.answer)

    def set_box_toggle(self, element):
        """set the attributes of a toggle box"""
        self.opened = Utils.get_tag_attribute(element, "opened", "jahia:value")

        self.content = Utils.get_tag_attribute(element, "content", "jahia:value")

    def set_box_include(self, element):
        """set the attributes of an include box"""
        url = Utils.get_tag_attribute(element, "url", "jahia:value")
        if "://people.epfl.ch/cgi-bin/getProfiles?" in url:
            url = url.replace("tmpl=", "WP_tmpl=")
            self.content = "[epfl_people url={} /]".format(url)
        else:
            self.content = '[remote_content url="{}"]'.format(url)

    def set_box_contact(self, element):
        """set the attributes of a contact box"""
        text = Utils.get_tag_attribute(element, "text", "jahia:value")

        self.content = text

    def set_box_xml(self, element):
        """set the attributes of a xml box"""
        xml = Utils.get_tag_attribute(element, "xml", "jahia:value")
        xslt = Utils.get_tag_attribute(element, "xslt", "jahia:value")

        self.content = "[xml xml=%s xslt=%s]" % (xml, xslt)

    def set_box_rss(self, element):
        """set the attributes of an rss box"""

        # Jahia options
        url = Utils.get_tag_attribute(element, "url", "jahia:value")
        nb_items = Utils.get_tag_attribute(element, "nbItems", "jahia:value")
        hide_title = Utils.get_tag_attribute(element, "hideTitle", "jahia:value")
        detail_items = Utils.get_tag_attribute(element, "detailItems", "jahia:value")

        # check if we have at least an url
        if not url:
            return

        # some values are in JSP tag, with use a default value instead
        if not nb_items.isdigit():
            nb_items = "5"

        # feedzy-rss options
        feeds = url
        max = nb_items
        feed_title = "yes"
        summary = "yes"

        if hide_title == "true":
            feed_title = "no"

        if detail_items != "true":
            summary = "no"

        self.content = "[feedzy-rss feeds=\"{}\" max=\"{}\" feed_title=\"{}\" summary=\"{}\" refresh=\"12_hours\"]"\
            .format(feeds, max, feed_title, summary)

    def set_box_links(self, element):
        """set the attributes of a links box"""
        self.content = self._parse_links_to_list(element)

    def set_box_unknown(self, element):
        """set the attributes of an unknown box"""
        self.content = "[%s]" % element.getAttribute("jcr:primaryType")

    def set_box_files(self, element):
        """set the attributes of a files box"""
        elements = element.getElementsByTagName("file")
        content = "<ul>"
        for e in elements:
            if e.ELEMENT_NODE != e.nodeType:
                continue
            # URL is like /content/sites/<site_name>/files/file
            # splitted gives ['', content, sites, <site_name>, files, file]
            # result of join is files/file and we add the missing '/' in front.
            file_url = '/'.join(e.getAttribute("jahia:value").split("/")[4:])
            file_url = '/' + file_url
            file_name = file_url.split("/")[-1]
            content += '<li><a href="{}">{}</a></li>'.format(file_url, file_name)
        content += "</ul>"
        self.content = content

    def set_box_syntax_highlight(self, element):
        """Set the attributes of a syntaxHighlight box"""
        content = "[enlighter]"
        content += Utils.get_tag_attribute(element, "code", "jahia:value")
        content += "[/enlighter]"
        self.content = content

    def set_box_key_visuals(self, element):
        """Handles keyVisualBox, which is actually a carousel of images.
        For the carousel to work in wordpress, we need the media IDs of the images,
        but we do not know these IDs before importing the media, so the content of the box
        is translated to parsable html and will be replaced by the adequate shortcode in the
        exporter.
        """
        elements = element.getElementsByTagName("image")
        content = "<ul>"
        for e in elements:
            if e.ELEMENT_NODE != e.nodeType:
                continue
            # URL is like /content/sites/<site_name>/files/file
            # splitted gives ['', content, sites, <site_name>, files, file]
            # result of join is files/file and we add the missing '/' in front.
            image_url = '/'.join(e.getAttribute("jahia:value").split("/")[4:])
            image_url = '/' + image_url
            content += '<li><img src="{}" /></li>'.format(image_url)
        content += "</ul>"
        self.content = content

    def _parse_links_to_list(self, element):
        """Handles link tags that can be found in linksBox and textBox"""
        elements = element.getElementsByTagName("link")
        content = "<ul>"
        for e in elements:
            if e.ELEMENT_NODE != e.nodeType:
                continue
            for jahia_tag in e.childNodes:
                if jahia_tag.ELEMENT_NODE != jahia_tag.nodeType:
                    continue
                if jahia_tag.tagName == "jahia:link":
                    # It happens that a link references a page that does not exist anymore
                    # observed on site dii
                    try:
                        page = self.site.pages_by_uuid[jahia_tag.getAttribute("jahia:reference")]
                    except KeyError as e:
                        continue
                    content += '<li><a href="{}">{}</a></li>'.format(page.pid, jahia_tag.getAttribute("jahia:title"))
                elif jahia_tag.tagName == "jahia:url":
                    url = jahia_tag.getAttribute("jahia:value")
                    title = jahia_tag.getAttribute("jahia:title")
                    content += '<li><a href="{}">{}</a></li>'.format(url, title)
        content += "</ul>"

        if content == "<ul></ul>":
            return ""

        return content

    def set_box_map(self, element):
        """set the attributes of a map box"""

        # parse info
        height = Utils.get_tag_attribute(element, "height", "jahia:value")
        width = Utils.get_tag_attribute(element, "width", "jahia:value")
        query = Utils.get_tag_attribute(element, "query", "jahia:value")

        # in the parser we can't know the current language.
        # so we assign a string that we will replace by the current language in the exporter
        lang = self.UPDATE_LANG

        self.content = '[epfl_map width="{}" height="{}" query="{}" lang="{}"]'.format(width, height, query, lang)

    def __str__(self):
        return self.type + " " + self.title
