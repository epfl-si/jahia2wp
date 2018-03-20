"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""


class MenuItem:
    """ To store menu item information """

    def __init__(self, txt, target, hidden):
        """ Constructor

        txt - Menu link text
        target - Can be several things
            -- Reference to another jahia page,using its uuid (like c058dc4f-247d-4b23-90d7-25e1206f7de3)
            -- hardcoded URL (absolute URL)
            -- link to sitemap (so equals 'sitemap')
            -- hardcoded URL to file (includes '/files/' in string)
            -- None if normal menu entry for page
        """
        self.txt = txt
        self.target = target
        if self.target:
            self.target = self.target.strip()
        self.hidden = hidden
        self.children = []
        self.children_sort_way = None

    def target_is_url(self):
        return False if self.target is None else self.target.startswith('http')

    def target_is_sitemap(self):
        return self.target == "sitemap"

    def target_is_file(self):
        return False if self.target is None else '/files/' in self.target

    def target_is_reference(self):
        # If it is not another possibility, it is a reference
        return not self.target_is_sitemap() and \
            not self.target_is_url() and \
            self.target is not None

    def sort_children(self, sort_way):
        self.children_sort_way = sort_way
        self.children.sort(key=lambda x: x.txt, reverse=(sort_way == 'desc'))
