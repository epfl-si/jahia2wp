"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""


class NavigationPage:
    """
    Jahia uses NavigationPages to define which pages are directly below a given page.
    A NavigationPage can be internal (another Jahia Page), or external
    (a URL, e.g. https://www.google.com).

    NavigationPages are used by Jahia to build the sitemap and the navigation, however
    you will probably want to use SitemapNode instead, e.g. site.sitemaps["en"] to get
    the English sitemap as a Node.
    """

    def __init__(self, parent, type, ref, title):
        self.parent = parent
        self.type = type
        self.ref = ref
        self.title = title

    @property
    def page(self):
        if self.ref in self.parent.site.pages_by_uuid:
            return self.parent.site.pages_by_uuid[self.ref]
        else:
            return None

    def __str__(self):
        return self.type + " " + self.ref + " " + self.title
