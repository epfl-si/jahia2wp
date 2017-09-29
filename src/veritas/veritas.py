""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017 """

import re

from utils import Utils


class VeritasValidor:
    """
    Validates a CSV file containing a list of WordPress metadata
    You can use https://regex101.com/ to validate your regex
    """

    # the csv delimiter
    DELIMITER = ","

    # the regex used to validate the url
    REGEX_URL = re.compile("^http(s)?://[a-zA-Z0-9.\-/]+$")

    # the regex used to validate the admin email
    REGEX_EMAIL = re.compile("(^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$)")

    # the regex used to validate the db name
    REGEX_DB_NAME = re.compile("^[a-z0-9]{8,16}$")

    # the regex used to validated a string
    REGEX_STRING = re.compile("^.+$")

    # the regex used to validate the site type
    REGEX_SITE_TYPE = re.compile("^wordpress$")

    # the regex used to validate openshift env
    REGEX_OPENSHIFT_ENV = re.compile("^(dev|int|ebreton|ejaep|lvenries|lboatto|gcharmier|lchaboudez)$")

    # the regex used to validate theme
    REGEX_THEME = re.compile("^EPFL$")

    # the regex used to validate yes/no
    REGEX_YES_NO = re.compile("^(yes|no)$")

    # the regex used to validate an integer
    REGEX_INTEGER = re.compile("^[0-9]+$")

    # the regex used to validate a lang
    REGEX_LANG = re.compile("^(fr|en|de|es|ro|gr|it)(,(fr|en|de|es|ro|gr|it))*$")

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

        columns = (
            ("wp_site_url", self.REGEX_URL, True),
            ("wp_default_site_title", self.REGEX_STRING, False),
            ("site_type", self.REGEX_SITE_TYPE, False),
            ("openshift_env", self.REGEX_OPENSHIFT_ENV, False),
            # category => no validation
            ("theme", self.REGEX_THEME, False),
            # status => no validation
            ("installs_locked", self.REGEX_YES_NO, False),
            ("updates_automatic", self.REGEX_YES_NO, False),
            ("langs", self.REGEX_LANG, False),
            ("owner_id", self.REGEX_INTEGER, False),
            ("responsible_id", self.REGEX_INTEGER, False),
            # unit => no validation
            # comment => no validation
        )

        for column in columns:
            self.columns.append(VeritasColumn(
                column_name=column[0],
                regex=column[1],
                is_unique=column[2]))

    def validate(self):
        """Validate the columns"""

        # check the regexp
        for column in self.columns:
            self.check_regex(
                regex=column.regex,
                column_name=column.column_name,
                message="invalid %s" % column.column_name)

        # check the uniqueness
        for column in self.columns:
            if column.is_unique:
                self.check_unique(
                    column_name=column.column_name,
                    message="%s is not unique" % column.column_name)

        # sort the errors by the line number
        self.errors.sort(key=lambda x: x.line)

    def check_regex(self, regex, column_name, message):
        """Check all the given column values with the given regex"""

        for line, row in enumerate(self.rows, start=1):
            if not regex.match(row[column_name]):
                self.add_error(line, column_name, "%s : %s" %
                               (message, row[column_name]))

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

    def __init__(self, column_name, regex, is_unique):
        """Constructor"""

        self.column_name = column_name
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
