"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""


class MenuItem:
    """ To store menu item information """

    def __init__(self, txt, target_url, hidden):

        self.txt = txt
        self.target_url = target_url
        self.hidden = hidden
        self.children = []

    def target_is_url(self):
        return False if self.target_url is None else self.target_url.startswith('http')

    def target_is_sitemap(self):
        return self.target_url == "sitemap"
