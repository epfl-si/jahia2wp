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

    def get_child_with_uuid(self, uuid, nb_recurse_max):
        """
        Returns child which have a jahia UUID equal to the one given as parameter
        :param uuid: UUID of the page we look for
        :param nb_recurse_max: Max number of recursive calls. This is to avoid infinite loop if circular references
                               between pages.
        :return: Page object or None if not found.
        """
        for child in self.children:
            # If found at this level
            if child.uuid == uuid:
                return child

            # If we can go to next level
            if nb_recurse_max > 0:
                # Not found a this level, recurse search to next level
                result = child.get_child_with_uuid(uuid, nb_recurse_max-1)
                # If found, return
                if result is not None:
                    return result

        return None

    def __str__(self):
        return self.pid + " " + self.template
