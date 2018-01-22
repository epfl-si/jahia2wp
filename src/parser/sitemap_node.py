from anytree import Node, RenderTree


class SitemapNode(Node):
    """
    A SitemapNode represents a node of the sitemap. The root node
    (the homepage) is available as a property of the Site class,
    e.g. site.sitemaps["en"] for the English sitemap. This class
    is an extension of Node, from the anytree library:

    https://pypi.python.org/pypi/anytree/1.0.1

    A SitemapNode can reference two types of pages:

    1. Internal pages, in which case the "page" property is the Page itself and the
       "ref" property is the Page's UUID.

    2. External pages, in which case the "page" property is None and the
       "ref" property is the external URL, e.g. https://www.google.com.
    """

    def __init__(self, name, page, ref, parent=None):
        super().__init__(name, parent)

        self.page = page
        self.ref = ref

    def print_node(self):
        """Print the node"""

        for pre, fill, node in RenderTree(self):
            print("%s%s" % (pre, node.name))

    @classmethod
    def from_navigation_page(cls, navigation_page, parent):
        """Create a SitemapNode from a NavigationPage"""

        return SitemapNode(
            name=navigation_page.title,
            page=navigation_page.page,
            ref=navigation_page.ref,
            parent=parent)
