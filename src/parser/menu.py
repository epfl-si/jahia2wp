"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""

from parser.menu_item import MenuItem


class Menu:
    """ To store menus """

    def __init__(self):

        self.root_menu = []

    def add_main_entry(self, txt, target):
        """
        Add entry to main menu

        txt - menu text
        target - menu target (ULR or page name)

        Ret : main menu entry id
        """

        menu_item = MenuItem(txt, target)

        self.root_menu.append(menu_item)

        return len(self.root_menu) - 1

    def add_sub_entry(self, txt, target, parent_id):

        return self.root_menu[parent_id].add_child(txt, target)
