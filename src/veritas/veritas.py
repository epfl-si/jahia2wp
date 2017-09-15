""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017 """

import csv
import re


class VeritasValidor:
    """Validates a CSV file containing a list of WordPress metadata"""

    # the csv delimiter
    DELIMITER = ","

    # the CSV path
    csv_path = ""

    # the regex used to validate the url
    regex_url = re.compile("^[a-zA-Z0-9.\-/]+$")

    # the regex used to validate the admin email
    regex_email = re.compile("(^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$)")

    # the regex used to validate the db name
    regex_db_name = re.compile("^[a-z0-9]{8,16}$")

    # the rows
    rows = []

    # the errors
    errors = []

    # the VeritasColumns
    columns = []

    def __init__(self, csv_path):
        """Constructor"""

        self.csv_path = csv_path

    def define_columns(self):
        """Define the columns"""

        # url
        self.columns.append(VeritasColumn(
            column_index=0,
            column_name="url",
            column_label="url",
            regex=self.regex_url,
            is_unique=True))

        # admin
        self.columns.append(VeritasColumn(
            column_index=1,
            column_name="admin",
            column_label="admin",
            regex=self.regex_email,
            is_unique=False))

        # db_name
        self.columns.append(VeritasColumn(
            column_index=2,
            column_name="db_name",
            column_label="db name",
            regex=self.regex_db_name,
            is_unique=True))

    def validate(self):
        """Validate the CSV file"""

        self.define_columns()

        with open(self.csv_path, 'r') as csvfile:
            reader = csv.reader(csvfile, delimiter=self.DELIMITER)

            # load the rows
            for row in reader:
                self.rows.append(row)

            # removes the first row containing the headers
            self.rows.pop(0)

            self.validate_columns()

    def validate_columns(self):
        """Validate the columns"""

        # check the regexp
        for column in self.columns:
            self.check_regex(
                regex=column.regex,
                column_index=column.column_index,
                column_name=column.column_name,
                message="invalid %s" % column.column_label)

        # check the uniqueness
        for column in self.columns:
            if column.is_unique:
                self.check_unique(
                    column_index=column.column_index,
                    column_name=column.column_name,
                    message="%s is not unique" % column.column_label)

        # sort the errors in the end to have them by line number
        self.errors.sort()

    def check_regex(self, regex, column_index, column_name, message):
        """Check all the given column values with the given regex"""

        line = 1

        for row in self.rows:
            if not regex.match(row[column_index]):
                self.add_error(line, column_name, "%s : %s" %
                               (message,
                                row[column_index]))

            line += 1

    def check_unique(self, column_index, column_name, message):
        """Check that all the values of the given column are unique"""

        line = 1

        unique = {}

        for row in self.rows:
            value = row[column_index]

            if value in unique:
                self.add_error(line, column_name, "%s : %s" %
                               (message,
                                row[column_index]))

            unique[value] = value

            line += 1

    def add_error(self, line, column_name, message):
        """Add the given error to the list of errors"""

        self.errors.append("Error line %s for column %s : %s"
                           % (line,
                              column_name,
                              message))

    def print_errors(self):
        """Prints the errors"""

        for error in self.errors:
            print(error)


class VeritasColumn:
    """A VeritasColumn represents a column in the CSV file"""

    column_index = 0
    column_name = ""
    column_label = ""
    # the regex used to validate the values in the column
    regex = ""
    # should all the values be unique in the column?
    is_unique = False

    def __init__(self, column_index, column_name, column_label, regex, is_unique):
        """Constructor"""

        self.column_index = column_index
        self.column_name = column_name
        self.column_label = column_label
        self.regex = regex
        self.is_unique = is_unique
