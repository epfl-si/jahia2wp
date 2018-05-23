"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import logging
from datetime import datetime
from urllib import parse
from urllib.parse import urlencode
from xml.dom import minidom

from bs4 import BeautifulSoup

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
    TYPE_SNIPPETS = "snippets"
    TYPE_SYNTAX_HIGHLIGHT = "syntaxHighlight"
    TYPE_KEY_VISUAL = "keyVisual"
    TYPE_MAP = "map"
    TYPE_GRID = "grid"

    # Mapping of known box types from Jahia to WP
    types = {
        "epfl:textBox": TYPE_TEXT,
        "epfl:coloredTextBox": TYPE_COLORED_TEXT,
        "epfl:peopleListBox": TYPE_PEOPLE_LIST,
        "epfl:infoscienceBox": TYPE_INFOSCIENCE,
        "epfl:actuBox": TYPE_ACTU,
        "epfl:mementoBox": TYPE_MEMENTO,
        "epfl:faqBox": TYPE_FAQ,
        "epfl:toggleBox": TYPE_TOGGLE,
        "epfl:htmlBox": TYPE_INCLUDE,
        "epfl:contactBox": TYPE_CONTACT,
        "epfl:xmlBox": TYPE_XML,
        "epfl:linksBox": TYPE_LINKS,
        "epfl:rssBox": TYPE_RSS,
        "epfl:filesBox": TYPE_FILES,
        "epfl:snippetsBox": TYPE_SNIPPETS,
        "epfl:syntaxHighlightBox": TYPE_SYNTAX_HIGHLIGHT,
        "epfl:keyVisualBox": TYPE_KEY_VISUAL,
        "epfl:mapBox": TYPE_MAP,
        "epfl:gridBox": TYPE_GRID
    }

    UPDATE_LANG = "UPDATE_LANG_BY_EXPORTER"

    def __init__(self, site, page_content, element, multibox=False):
        # attributes
        self.site = site
        self.page_content = page_content
        self.type = ""
        self.shortcode_name = ""
        self.set_type(element)
        self.title = Utils.get_tag_attribute(element, "boxTitle", "jahia:value")
        self.content = ""

        # the shortcode attributes with URLs that must be fixed by the wp_exporter
        self.shortcode_attributes_to_fix = []

        # parse the content
        if self.type:
            self.set_content(element, multibox)

    def set_type(self, element):
        """
        Sets the box type
        """
        type = element.getAttribute("jcr:primaryType")

        if not type:
            logging.warning("Box has no type")
        elif type in self.types:
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
        # snippets
        elif self.TYPE_SNIPPETS == self.type:
            self.set_box_snippets(element)
        # syntaxHighlight
        elif self.TYPE_SYNTAX_HIGHLIGHT == self.type:
            self.set_box_syntax_highlight(element)
        # keyVisual
        elif self.TYPE_KEY_VISUAL == self.type:
            self.set_box_key_visuals(element)
        # Map
        elif self.TYPE_MAP == self.type:
            self.set_box_map(element)
        # Grid
        elif self.TYPE_GRID == self.type:
            self.set_box_grid(element)
        # unknown
        else:
            self.set_box_unknown(element)

    def _set_scheduler_box(self, element, content):
        """set the attributes of a scheduler box"""

        self.shortcode_name = "epfl_scheduler"

        start_datetime = Utils.get_tag_attribute(element, "comboList", "jahia:validFrom")
        end_datetime = Utils.get_tag_attribute(element, "comboList", "jahia:validTo")

        if not start_datetime and not end_datetime:
            logging.info("Scheduler has no start date and no end date, simply using content")
            return content

        today = datetime.now().strftime("%Y-%m-%d")

        start_date = ""
        start_time = ""

        if "T" in start_datetime:
            start_date = start_datetime.split("T")[0]
            start_time = start_datetime.split("T")[1]

        end_date = ""
        end_time = ""

        if "T" in end_datetime:
            end_date = end_datetime.split("T")[0]
            end_time = end_datetime.split("T")[1]

        # check if we have a start date in the past and no end date
        if start_date and not end_date:
            if start_date < today:
                logging.info("Scheduler has a start date in the past ({}) and no end date,"
                             " simply using content".format(start_date))
                return content

        # we don't need to check if end_date > today
        # In case end_date < today the shortcode display nothing
        if not start_date and end_date:
            start_date = today

        return '[{} start_date="{}" end_date="{}" start_time="{}" end_time="{}"]{}[/{}]'.format(
            self.shortcode_name,
            start_date,
            end_date,
            start_time,
            end_time,
            content,
            self.shortcode_name
        )

    def set_box_grid(self, element):
        """
        Set attributes for a grid box.
        A grid box is a <div> containing others <div> with a specified size (defined by the layout, "large" or
        "default"), image, text and link.
        FIXME: Handle <boxTitle> field (was empty when box support has been added so no idea how it is displayed..)
        FIXME: Handle <text> field (was empty when box support has been added so no idea how it is displayed..)
        FIXME: Handle attribute GridListList -> "jahia:sortHandler" if needed
        :param element:
        :return:
        """
        shortcode_outer_name = "epfl_grid"
        shortcode_inner_name = "epfl_gridElem"

        self.shortcode_name = shortcode_outer_name

        # register the shortcodes
        self.site.register_shortcode(shortcode_inner_name, ["link", "image"], self)

        self.content = '[{}]\n'.format(shortcode_outer_name)

        elements = element.getElementsByTagName("gridList")

        for e in elements:

            layout_infos = Utils.get_tag_attribute(e, "layout", "jahia:value")
            soup = BeautifulSoup(layout_infos, 'html5lib')
            layout = soup.find('jahia-resource').get('default-value')

            # Retrieve info
            link = Utils.get_tag_attribute(e, "jahia:url", "jahia:value")
            image = Utils.get_tag_attribute(e, "image", "jahia:value")
            title = Utils.get_tag_attribute(e, "jahia:url", "jahia:title")

            # Escape if necessary
            title = title.replace('"', '\\"')

            self.content += '[{} layout="{}" link="{}" title="{}" image="{}"][/{}]\n'.format(
                shortcode_inner_name, layout, link, title, image, shortcode_inner_name)

        self.content += "[/{}]".format(shortcode_outer_name)

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
            links_list = element.getElementsByTagName("linksList")
            if links_list:
                content += self._parse_links_to_list(links_list[0])
        else:

            # Looking for sort information. If found, they looks like :
            # "created;desc;true;true"
            sort_infos = Utils.get_tag_attribute(element, "comboListList", "jahia:sortHandler")

            # If we have information about sorting, we extract them
            if sort_infos != "":
                # It seems that sort field is corresponding to "jcr:<sort_field>" attribute in XML
                sort_field = sort_infos.split(";")[0]
                sort_way = sort_infos.split(";")[1]
            else:
                # If we don't have information about sorting, we still have to keep boxes order. So index will
                # be used to add each encountered boxes at an index.
                box_index = 0
                # To sort by index to keep the correct order.
                sort_way = "asc"

            box_list = {}

            combo_list = element.getElementsByTagName("comboList")
            for combo in combo_list:
                # We generate box content
                box_content = Utils.get_tag_attribute(combo, "text", "jahia:value")
                # linksList contain <link> tags exactly like linksBox, so we can just reuse
                # the same code used to parse linksBox.
                box_content += self._parse_links_to_list(combo)

                # if we have sort infos, we have to get field information in XML
                if sort_infos != "":
                    box_key = combo.getAttribute('jcr:{}'.format(sort_field))
                else:
                    box_key = box_index
                    box_index += 1
                # Saving box content with sort field association
                box_list[box_key] = box_content

            # We sort boxes with correct information. As output, we will have a list of Tuples with dict key as
            # first element (index 0) and dict value as second element (index 1)
            box_list = sorted(box_list.items(), reverse=(sort_way == 'desc'))

            # For all boxes content
            content = ""

            for box_key, box_content in box_list:
                content += box_content

            # scheduler shortcode
            if Utils.get_tag_attribute(element, "comboList", "jahia:ruleType") == "START_AND_END_DATE":
                content = self._set_scheduler_box(element, content)

        self.content = content

    @staticmethod
    def _extract_epfl_news_parameters(url):
        """
        Extract parameters form url
        """
        parameters = parse.parse_qs(parse.urlparse(url).query)

        if 'channel' in parameters:
            channel_id = parameters['channel'][0]
        else:
            channel_id = ""
            logging.error("News Shortcode - channel ID is missing")

        if 'lang' in parameters:
            lang = parameters['lang'][0]
        else:
            lang = ""
            logging.warning("News Shortcode - lang is missing")

        if 'template' in parameters:
            template = parameters['template'][0]
        else:
            template = ""
            logging.warning("News Shortcode - template is missing")

        # in actu.epfl.ch if sticker parameter exists, sticker is not displayed
        # (whatever the value of sticker parameter)
        # if sticker parameter does not exist, sticker is displayed
        if 'sticker' in parameters:
            stickers = "no"
        else:
            stickers = "yes"

        category = ""
        if 'category' in parameters:
            category = parameters['category'][0]

        themes = ""
        if 'themes' in parameters:
            themes = parameters['theme']

        projects = ""
        if 'project' in parameters:
            projects = parameters['project']

        return channel_id, lang, template, category, themes, stickers, projects

    def set_box_people_list(self, element):
        """
        Set the attributes of a people list box

        More information here:
        https://c4science.ch/source/kis-jahia6-dev/browse/master/core/src/main/webapp/common/box/display/peopleListBoxDisplay.jsp
        """
        self.shortcode_name = "epfl_people"

        BASE_URL = "https://people.epfl.ch/cgi-bin/getProfiles?"

        # prepare a dictionary with all GET parameters
        parameters = {}

        # parse the unit parameter
        parameters['unit'] = Utils.get_tag_attribute(element, "query", "jahia:value")

        # parse the template html
        template_html = Utils.get_tag_attribute(element, "template", "jahia:value")

        # check if we have an HTML template
        if not template_html:
            logging.warning("epfl_people: no HTML template set")
            self.content = "[epfl_people error: no HTML template set]"
            return

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
        parameters['tmpl'] = "WP_" + template

        # in the parser we can't know the current language.
        # so we assign a string that we will replace by the current language in the exporter
        parameters['lang'] = self.UPDATE_LANG

        url = "{}{}".format(BASE_URL, urlencode(parameters))
        self.content = '[{} url="{}" /]'.format(self.shortcode_name, url)

    def set_box_actu(self, element):
        """set the attributes of an actu box"""

        # extract parameters from the old url of webservice
        channel_id, lang, template, category, themes, stickers, projects = self._extract_epfl_news_parameters(
            Utils.get_tag_attribute(element, "url", "jahia:value")
        )
        self.shortcode_name = "epfl_news"
        html_content = '[{} channel="{}" lang="{}" template="{}" '.format(
            self.shortcode_name,
            channel_id,
            lang,
            template
        )
        if category:
            html_content += 'category="{}" '.format(category)
        if themes:
            html_content += 'themes="{}" '.format(",".join(themes))
        if stickers:
            html_content += 'stickers="{}" '.format(stickers)
        if projects:
            html_content += 'projects="{}" '.format(",".join(projects))

        html_content += '/]'

        self.content = html_content

    @staticmethod
    def _extract_epfl_memento_parameters(url):
        """
        Extract parameters form url
        """
        parameters = parse.parse_qs(parse.urlparse(url).query)

        if 'memento' in parameters:
            memento_name = parameters['memento'][0]
        else:
            memento_name = ""
            logging.error("Memento Shortcode - event ID is missing")

        if 'lang' in parameters:
            lang = parameters['lang'][0]
        else:
            lang = ""
            logging.error("Memento Shortcode - lang is missing")

        if 'template' in parameters:
            template = parameters['template'][0]
        else:
            template = ""
            logging.error("Memento Shortcode - template is missing")

        period = ""
        if 'period' in parameters:
            period = parameters['period'][0]

        color = ""
        if 'color' in parameters:
            color = parameters['color'][0]

        filters = ""
        if 'filters' in parameters:
            filters = parameters['filters'][0]

        category = ""
        if 'category' in parameters:
            category = parameters['category'][0]

        reorder = ""
        if 'reorder' in parameters:
            reorder = parameters['reorder'][0]

        return memento_name, lang, template, period, color, filters, category, reorder

    def set_box_memento(self, element):
        """set the attributes of a memento box"""

        # extract parameters from the old url of webservice
        memento_name, lang, template, period, color, filters, category, reorder = \
            self._extract_epfl_memento_parameters(
                Utils.get_tag_attribute(element, "url", "jahia:value")
            )
        self.shortcode_name = "epfl_memento"
        html_content = '[{} memento="{}" lang="{}" template="{}" '.format(
            self.shortcode_name,
            memento_name,
            lang,
            template
        )
        if period:
            html_content += 'period="{}" '.format(period)
        if color:
            html_content += 'color="{}" '.format(color)
        if filters:
            html_content += 'filters="{}" '.format(filters)
        if category:
            html_content += 'category="{}" '.format(category)
        if reorder:
            html_content += 'reorder="{}" '.format(reorder)

        html_content += '/]'

        self.content = html_content

    def set_box_infoscience(self, element):
        """set the attributes of a infoscience box"""

        self.shortcode_name = "epfl_infoscience"

        url = Utils.get_tag_attribute(element, "url", "jahia:value")

        self.content = "[{} url={}]".format(self.shortcode_name, url)

    def set_box_faq(self, element):
        """set the attributes of a faq box

        FIXME: Handle boxTitle option
        FIXME: Handle filesList option in FAQ item
        FIXME: Handle linksList option in FAQ item
        """

        shortcode_outer_name = "epfl_faq"
        shortcode_inner_name = "epfl_faqItem"

        self.shortcode_name = shortcode_outer_name

        # register the shortcode
        self.site.register_shortcode(shortcode_inner_name, ["link", "image"], self)

        self.content = '[{}]\n'.format(shortcode_outer_name)

        # Looking for entries
        faq_entries = element.getElementsByTagName("faqList")

        for entry in faq_entries:

            # Get question and escape if necessary
            question = Utils.get_tag_attribute(entry, "question", "jahia:value").replace('"', '\\"')

            # Get answer
            answer = Utils.get_tag_attribute(entry, "answer", "jahia:value")

            self.content += '[{} question="{}"]{}[/{}]\n'.format(
                shortcode_inner_name, question, answer, shortcode_inner_name)

        self.content += "[/{}]".format(shortcode_outer_name)

    def set_box_toggle(self, element):
        """set the attributes of a toggle box"""

        self.shortcode_name = 'epfl_toggle'

        if Utils.get_tag_attribute(element, "opened", "jahia:value"):
            state = 'open'
        else:
            state = 'close'

        content = '[epfl_toggle title="{}" state="{}"]'.format(self.title, state)
        content += Utils.get_tag_attribute(element, "content", "jahia:value")
        content += '[/epfl_toggle]'

        self.content = content

    def set_box_include(self, element):
        """set the attributes of an include box"""
        url = Utils.get_tag_attribute(element, "url", "jahia:value")
        if "://people.epfl.ch/cgi-bin/getProfiles?" in url:
            url = url.replace("tmpl=", "tmpl=WP_")

            self.shortcode_name = "epfl_people"

            self.content = '[{} url="{}" /]'.format(self.shortcode_name, url)
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

        self.shortcode_name = "epfl_xml"

        self.content = '[{} xml="{}" xslt="{}"]'.format(self.shortcode_name, xml, xslt)

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

        self.content = "[feedzy-rss feeds=\"{}\" max=\"{}\" feed_title=\"{}\" summary=\"{}\" refresh=\"12_hours\"]" \
            .format(feeds, max, feed_title, summary)

    def set_box_links(self, element):
        """set the attributes of a links box"""
        self.content = self._parse_links_to_list(element)

    def set_box_unknown(self, element):
        """set the attributes of an unknown box"""
        self.content = "[{}]".format(element.getAttribute("jcr:primaryType"))

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

    def set_box_snippets(self, element):
        """set the attributes of a snippets box"""

        self.shortcode_name = "epfl_snippets"

        # register the shortcode
        self.site.register_shortcode(self.shortcode_name, ["url", "image", "big_image"], self)

        # check if the list is not empty
        if not element.getElementsByTagName("snippetListList"):
            return

        snippets = element.getElementsByTagName("snippetListList")[0].getElementsByTagName("snippetList")

        self.content = ""

        for snippet in snippets:
            title = Utils.get_tag_attribute(snippet, "title", "jahia:value")
            subtitle = Utils.get_tag_attribute(snippet, "subtitle", "jahia:value")
            description = Utils.get_tag_attribute(snippet, "description", "jahia:value")
            image = Utils.get_tag_attribute(snippet, "image", "jahia:value")
            big_image = Utils.get_tag_attribute(snippet, "bigImage", "jahia:value")
            enable_zoom = Utils.get_tag_attribute(snippet, "enableImageZoom", "jahia:value")

            # Fix path if necessary
            if "/files" in image:
                image = image[image.rfind("/files"):]

            # escape
            title = title.replace('"', '\\"')
            subtitle = subtitle.replace('"', '\\"')

            url = ""

            # url
            if element.getElementsByTagName("url"):
                # first check if we have a <jahia:url> (external url)
                url = Utils.get_tag_attribute(snippet, "jahia:url", "jahia:value")

                # if not we might have a <jahia:link> (internal url)
                if url == "":
                    uuid = Utils.get_tag_attribute(snippet, "jahia:link", "jahia:reference")

                    if uuid in self.site.pages_by_uuid:
                        page = self.site.pages_by_uuid[uuid]

                        url = "/page-{}-{}.html".format(page.pid, self.page_content.language)

            self.content += '[{} url="{}" title="{}" subtitle="{}" image="{}"' \
                            ' big_image="{}" enable_zoom="{}"]{}[/{}]'.format(self.shortcode_name,
                                                                              url,
                                                                              title,
                                                                              subtitle,
                                                                              image,
                                                                              big_image,
                                                                              enable_zoom,
                                                                              description,
                                                                              self.shortcode_name)

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
        """Handles link tags that can be found in linksBox and textBox

        Structure is the following:
        <linksList>
            <links>
                <linkDesc></linkDesc>  <-- It seems that sometimes this is not present in Jahia export
                <link>
                    <jahia:url>     <-- If not present, 'jahia:link' is present
                    <jahia:link>    <-- If not present, 'jahia:url' is present
                </link>
            </links>
        </linksList>
        """
        elements = element.getElementsByTagName("links")
        content = "<ul>"
        for e in elements:
            if e.ELEMENT_NODE != e.nodeType:
                continue

            link_html = ""
            desc = ""
            # Going through 'linkDesc' and 'link' nodes
            for link_node in e.childNodes:
                if link_node.ELEMENT_NODE != link_node.nodeType:
                    continue

                if link_node.tagName == "linkDesc":
                    desc = link_node.getAttribute("jahia:value")
                elif link_node.tagName == "link":

                    # Going through node containing link. It can be 'jahia:link' or 'jahia:url' node.
                    for jahia_tag in link_node.childNodes:
                        if jahia_tag.ELEMENT_NODE != jahia_tag.nodeType:
                            continue
                        if jahia_tag.tagName == "jahia:link":
                            # It happens that a link references a page that does not exist anymore
                            # observed on site dii
                            try:
                                page = self.site.pages_by_uuid[jahia_tag.getAttribute("jahia:reference")]
                            except KeyError as e:
                                continue
                            link_html = '<a href="{}">{}</a>'.format(page.pid, jahia_tag.getAttribute("jahia:title"))

                        elif jahia_tag.tagName == "jahia:url":
                            link_html = '<a href="{}">{}</a>'.format(jahia_tag.getAttribute("jahia:value"),
                                                                     jahia_tag.getAttribute("jahia:title"))

            content += '<li>{}{}</li>'.format(link_html, desc)

        content += "</ul>"

        if content == "<ul></ul>":
            return ""

        return content

    def set_box_map(self, element):
        """set the attributes of a map box"""

        self.shortcode_name = "epfl_map"

        # parse info
        height = Utils.get_tag_attribute(element, "height", "jahia:value")
        width = Utils.get_tag_attribute(element, "width", "jahia:value")
        query = Utils.get_tag_attribute(element, "query", "jahia:value")

        # in the parser we can't know the current language.
        # so we assign a string that we will replace by the current language in the exporter
        lang = self.UPDATE_LANG

        self.content = '[{} width="{}" height="{}" query="{}" lang="{}"]'.format(self.shortcode_name,
                                                                                 width,
                                                                                 height,
                                                                                 query,
                                                                                 lang)

    def is_shortcode(self):
        return self.shortcode_name != ""

    def is_empty(self):
        return self.title == "" and self.content == ""

    def __str__(self):
        return self.type + " " + self.title
