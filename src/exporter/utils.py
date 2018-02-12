"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os


class Utils:

    @staticmethod
    def escape_quotes(str):
        return str.replace('"', '\\"')

    @staticmethod
    def get_menu_id(str):
        return str.replace('\n', '')

    @staticmethod
    def convert_bytes(num):
        """
        This function will convert bytes to MB.... GB... etc
        """
        for x in ['bytes', 'KB', 'MB', 'GB', 'TB']:
            if num < 1024.0:
                return "%3.1f %s" % (num, x)
            num /= 1024.0
