""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017 """

import re

from utils import Utils


class VeritasValidor:
    """Validates a CSV file containing a list of WordPress metadata"""

    # the csv delimiter
    DELIMITER = ","

    # the regex used to validate the url
    REGEX_URL = re.compile("^[a-zA-Z0-9.\-/]+$")

    # the regex used to validate the admin email
    REGEX_EMAIL = re.compile("(^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$)")

    # the regex used to validate the db name
    REGEX_DB_NAME = re.compile("^[a-z0-9]{8,16}$")

    def __init__(self, csv_path):
        """Constructor"""

        self.csv_path = csv_path

        # the rows
        self.rows = []

        # the VeritasErrors
        self.errors = []

        # the VeritasColumns
        self.columns = []

        # define the columns
        self.define_columns()

        # load the rows
        self.rows = Utils.csv_to_dict(file_path=self.csv_path, delimiter=self.DELIMITER)

    def define_columns(self):
        """Define the columns"""

        # url
        self.columns.append(VeritasColumn(
            column_name="url",
            column_label="url",
            regex=self.REGEX_URL,
            is_unique=True))

        # admin
        self.columns.append(VeritasColumn(
            column_name="admin",
            column_label="admin",
            regex=self.REGEX_EMAIL,
            is_unique=False))

        # db_name
        self.columns.append(VeritasColumn(
            column_name="db_name",
            column_label="db name",
            regex=self.REGEX_DB_NAME,
            is_unique=True))

    def validate(self):
        """Validate the columns"""

        # check the regexp
        for column in self.columns:
            self.check_regex(
                regex=column.regex,
                column_name=column.column_name,
                message="invalid %s" % column.column_label)

        # check the uniqueness
        for column in self.columns:
            if column.is_unique:
                self.check_unique(
                    column_name=column.column_name,
                    message="%s is not unique" % column.column_label)

        # sort the errors in the end to have them by line number
        # self.errors.sort()

    def check_regex(self, regex, column_name, message):
        """Check all the given column values with the given regex"""

        for line, row in enumerate(self.rows, start=1):
            if not regex.match(row[column_name]):
                self.add_error(line, column_name, "%s : %s" %
                               (message,
                                row[column_name]))

    def check_unique(self, column_name, message):
        """Check that all the values of the given column are unique"""

        unique = {}

        for line, row in enumerate(self.rows, start=1):
            if row[column_name] in unique:
                self.add_error(line, column_name, "%s : %s" %
                               (message,
                                row[column_name]))

            unique[row[column_name]] = row[column_name]

    def add_error(self, line, column_name, message):
        """Add the given error to the list of errors"""

        error = VeritasError(line=line, column_name=column_name, message=message)

        self.errors.append(error)

    def print_errors(self):
        """Prints the errors"""

        for error in self.errors:
            print(error.message)


class VeritasColumn:
    """A VeritasColumn represents a column in the CSV file"""

    def __init__(self, column_name, column_label, regex, is_unique):
        """Constructor"""

        self.column_name = column_name
        self.column_label = column_label
        # the regex used to validate the values in the column
        self.regex = regex
        # should all the values be unique in the column?
        self.is_unique = is_unique


class VeritasError:
    """An error in the CVS file"""

    def __init__(self, line, column_name, message):
        """Constructor"""

        self.line = line
        self.column_name = column_name
        self.message = "Error line %s for column %s : %s" % (line, column_name, message)
