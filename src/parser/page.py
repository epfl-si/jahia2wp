"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""

from utils import Utils


class Page:
    """A Jahia Page. Has 1 to N Jahia Boxes"""

    def __init__(self, site, element):
        # common data for all languages
        self.pid = element.getAttribute("jahia:pid")
        self.uuid = element.getAttribute("jcr:uuid")
        self.site = site
        self.template = element.getAttribute("jahia:template")
        self.parent = None
        self.children = []
        # the page level. 0 is for the homepage, direct children are
        # at level 1, grandchildren at level 2, etc.
        self.level = 0

        # the PageContents, one for each language. The dict key is the
        # language, the dict value is the PageContent
        self.contents = {}

        # update the number of templates
        Utils.increment_count(self.site.num_templates, self.template)

        # if we have a sitemap we don't want to parse the
        # page and add it to it's parent, so we stop here
        if "sitemap" == self.template:
            return

        # find the Page parent
        self.set_parent(element)

    def is_homepage(self):
        """
        Return True if the page is the homepage
        """
        return self.parent is None

    def has_children(self):
        """
        Return True if the page has children
        """
        return len(self.children) > 0

    def set_parent(self, element):
        """
        Find the page parent
        """
        element_parent = element.parentNode

        while "jahia:page" != element_parent.nodeName:
            element_parent = element_parent.parentNode

            # we reached the top of the document
            if not element_parent:
                break

        if element_parent:
            self.parent = self.site.pages_by_pid[element_parent.getAttribute("jahia:pid")]
            self.parent.children.append(self)

            # calculate the page level
            self.level = 1

            parent_page = self.parent

            while not parent_page.is_homepage():
                self.level += 1

                parent_page = parent_page.parent

    def __str__(self):
        return self.pid + " " + self.template
