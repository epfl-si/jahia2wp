"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""


class MenuItem:
    """ To store menu item information """

    def __init__(self, txt, target):

        self.txt = txt
        self.target = target
        self.children = []

    def is_target_url(self):

        return self.target is not None

    def add_child(self, txt, target):
        """
        Add child to current menu entry

        txt - menu text
        target - menu target (URL or page name)
        """

        menu_item = MenuItem(txt, target)
        self.children.append(menu_item)

        return len(self.children) - 1
