"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
from utils import Utils
import xml.dom


class Box:
    """A Jahia Box. Can be of type text, infoscience, etc."""

    # WP box types
    TYPE_TEXT = "text"
    TYPE_COLORED_TEXT = "coloredText"
    TYPE_INFOSCIENCE = "infoscience"
    TYPE_ACTU = "actu"
    TYPE_MEMENTO = "memento"
    TYPE_FAQ = "faq"
    TYPE_TOGGLE = "toggle"
    TYPE_INCLUDE = "include"
    TYPE_CONTACT = "contact"
    TYPE_XML = "xml"
    TYPE_LINKS = "links"

    # Mapping of known box types from Jahia to WP
    types = {
        "epfl:textBox": TYPE_TEXT,
        "epfl:coloredTextBox": TYPE_COLORED_TEXT,
        "epfl:infoscienceBox": TYPE_INFOSCIENCE,
        "epfl:actuBox": TYPE_ACTU,
        "epfl:mementoBox": TYPE_MEMENTO,
        "epfl:faqContainer": TYPE_FAQ,
        "epfl:toggleBox": TYPE_TOGGLE,
        "epfl:htmlBox": TYPE_INCLUDE,
        "epfl:contactBox": TYPE_CONTACT,
        "epfl:xmlBox": TYPE_XML,
        "epfl:linksBox": TYPE_LINKS
    }

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
        # unknown
        else:
            self.set_box_unknown(element)

    def set_box_text(self, element, multibox=False):
        """set the attributes of a text box"""

        if not multibox:
            self.content = Utils.get_tag_attribute(element, "text", "jahia:value")
        else:
            # Concatenate HTML content of many boxes
            content = ""
            elements = element.getElementsByTagName("text")
            for element in elements:
                content += element.getAttribute("jahia:value")
            self.content = content

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

        self.content = "[include url=%s]" % url

    def set_box_contact(self, element):
        """set the attributes of a contact box"""
        text = Utils.get_tag_attribute(element, "text", "jahia:value")

        self.content = text

    def set_box_xml(self, element):
        """set the attributes of a xml box"""
        xml = Utils.get_tag_attribute(element, "xml", "jahia:value")
        xslt = Utils.get_tag_attribute(element, "xslt", "jahia:value")

        self.content = "[xml xml=%s xslt=%s]" % (xml, xslt)

    def set_box_links(self, element):
        """set the attributes of a links box"""
        elements = element.getElementsByTagName("link")
        content = "<ul>"
        for e in elements:
            if e.ELEMENT_NODE != e.nodeType:
                continue
            for jahia_tag in e.childNodes:
                if jahia_tag.ELEMENT_NODE != jahia_tag.nodeType:
                    continue
                if jahia_tag.tagName == "jahia:link":
                    page = self.site.pages_by_uuid[jahia_tag.getAttribute("jahia:reference")]
                    content += "<li><a href={}>{}</a></li>".format(page.pid, jahia_tag.getAttribute("jahia:title"))
                elif jahia_tag.tagName == "jahia:url":
                    url = jahia_tag.getAttribute("jahia:value")
                    title = jahia_tag.getAttribute("jahia:title")
                    content += "<li><a href={}>{}</a></li>".format(url, title)
        content += "</ul>"

        self.content = content

    def set_box_unknown(self, element):
        """set the attributes of an unknown box"""
        self.content = "[%s]" % element.getAttribute("jcr:primaryType")

    def __str__(self):
        return self.type + " " + self.title
