import csv


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
