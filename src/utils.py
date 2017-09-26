"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os
import csv
import string


class Utils:

    @staticmethod
    def csv_to_dict(file_path, delimiter=','):
        """Returns the rows of the given CSV file as a list of dicts"""
        rows = []
        with open(file_path) as csvfile:
            reader = csv.DictReader(csvfile, delimiter=delimiter)
            for row in reader:
                rows.append(row)
        return rows

    @staticmethod
    def generate_password(length):
        """
        Generate a random password
        """
        chars = string.ascii_letters + string.digits + '-+'
        password = ''

        for i in range(length):
            password += chars[int(os.urandom(1)[0]) % len(chars)]

        return password
