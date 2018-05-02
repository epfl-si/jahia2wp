"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import re


class MenuItem:
    """ To store menu item information """

    def __init__(self, txt, points_to, hidden):
        """ Constructor

        txt - Menu link text
        points_to - On what link is pointing to. Can be several things
            -- Reference to another jahia page,using its uuid (like c058dc4f-247d-4b23-90d7-25e1206f7de3)
            -- hardcoded URL (absolute URL)
            -- link to sitemap (so equals 'sitemap')
            -- hardcoded URL to file (includes '/files/' in string)
            -- None if normal menu entry for page
        """
        self.txt = txt
        self.target = None
        self.points_to = points_to
        if self.points_to:
            self.points_to = self.points_to.strip()

            # If is link and value contains a specific target
            # ex: http://tmclub.eu/signup.php" target="_blank
            if self.points_to_url() and 'target=' in self.points_to.lower():
                # Extracting target
                # http://tmclub.eu/signup.php" target="_blank
                # To
                # _blank
                target = re.findall(r'target="([^".]*)', self.points_to, re.IGNORECASE)
                if target:
                    self.target = target[0]
                    # Extracting URL
                    # http://tmclub.eu/signup.php" target="_blank
                    # To
                    # http://tmclub.eu/signup.php
                    self.points_to = re.sub(r'target="{}("?)|"'.format(self.target),
                                            '',
                                            self.points_to,
                                            0,
                                            re.IGNORECASE).strip()

        self.hidden = hidden
        self.children = []
        self.children_sort_way = None

    def points_to_url(self):
        return False if self.points_to is None else self.points_to.startswith('http')

    def points_to_sitemap(self):
        return self.points_to == "sitemap"

    def points_to_file(self):
        return False if self.points_to is None else '/files/' in self.points_to

    def sort_children(self, sort_way):
        self.children_sort_way = sort_way
        self.children.sort(key=lambda x: x.txt, reverse=(sort_way == 'desc'))
