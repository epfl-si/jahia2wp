"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""


class MenuItem:
    """ To store menu item information """

    def __init__(self, txt, target_url, hidden):

        self.txt = txt
        self.target_url = target_url
        self.hidden = hidden
        self.children = []

    def target_is_url(self):

        return self.target_url is not None

    def add_child(self, txt, target_url, hidden):
        """
        Add child to current menu entry

        txt - menu text
        target - menu target (URL or page name)

        Ret : sub menu entry index
        """

        menu_item = MenuItem(txt, target_url, hidden)
        self.children.append(menu_item)

        return len(self.children) - 1
