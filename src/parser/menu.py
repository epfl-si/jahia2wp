"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""

from parser.menu_item import MenuItem


class Menu:
    """ To store root menus entries and their children """

    def __init__(self):

        self.root_menu = []

    def add_main_entry(self, txt, target_url):
        """
        Add entry to main menu

        txt - menu text
        target_url - menu target ULR

        Ret : main menu entry index
        """

        menu_item = MenuItem(txt, target_url)

        self.root_menu.append(menu_item)

        return len(self.root_menu) - 1

    def add_sub_entry(self, txt, target, parent_index):

        return self.root_menu[parent_index].add_child(txt, target)

    def get_sub_entries(self, parent_index):
        """
        Returns root menu sub menu entries

        parent_index - Index of root menu entry
        """
        return self.root_menu[parent_index]

    def target_is_url(self, index):
        return self.root_menu[index].target_is_url()

    def txt(self, index):
        return self.root_menu[index].txt

    def target_url(self, index):
        return self.root_menu[index].target_url

    def nb_main_entries(self):
        return len(self.root_menu)
