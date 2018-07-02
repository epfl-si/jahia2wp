"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018"""

from collections import OrderedDict


class BoxSortedGroup:
    """ To group boxes that have to be sort using on of their property field """

    def __init__(self, uuid, sort_field, sort_way):
        """
        Class constructor

        :param uuid: uuid of sort handler
        :param sort_field: field used to sort
        :param sort_way: sort way ('asc', 'desc')
        """
        self.uuid = uuid
        self.sort_field = sort_field
        self.sort_way = sort_way.lower()
        self.boxes = OrderedDict()

    def add_box_to_sort(self, box, sort_field_value):
        """
        Add a box to the sort group.
        :param box: Instance of Box class or shortcode representing a Box
        :param sort_field_value: value to use to sort the group
        :return:
        """
        self.boxes[sort_field_value] = box

    def get_sorted_boxes(self):
        """
        Returns a list with sorted boxes
        Sort and returns this way is the only working way I found. Maybe it's possible to do better with less code lines
        :return:
        """
        ordered_keys = sorted(self.boxes, reverse=(self.sort_way == 'desc'))

        box_list = []
        for key in ordered_keys:
            box_list.append(self.boxes[key])

        return box_list
