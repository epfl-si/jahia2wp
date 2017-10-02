""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017 """
from django.core.validators import URLValidator, ValidationError

from utils import Utils

from .validators import validate_integer, validate_string, validate_yes_or_no, \
    validate_openshift_env, validate_site_type, validate_theme, validate_languages


class VeritasValidor:
    """
    Validates a CSV file containing a list of WordPress metadata
    You can use https://regex101.com/ to validate your regex
    """

    # the csv delimiter
    DELIMITER = ","

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

        for name, validator, is_unique in (
            ("wp_site_url", URLValidator(), True),
            ("wp_default_site_title", validate_string, False),
            ("site_type", validate_site_type, False),
            ("openshift_env", validate_openshift_env, False),
            # category => no validation
            ("theme", validate_theme, False),
            # status => no validation
            ("installs_locked", validate_yes_or_no, False),
            ("updates_automatic", validate_yes_or_no, False),
            ("langs", validate_languages, False),
            ("owner_id", validate_integer, False),
            ("responsible_id", validate_integer, False),
            # unit => no validation
            # comment => no validation
        ):
            self.columns.append(VeritasColumn(name, validator, is_unique))

    def validate(self):
        """Validate the columns"""

        # check the regexp
        for column in self.columns:
            self.check_validators(column)

        # check the uniqueness
        for column in self.columns:
            if column.is_unique:
                self.check_unique(column)

        # sort the errors by the line number
        self.errors.sort(key=lambda x: x.line)

    def check_validators(self, column, message=None):
        """Check all the given column values with the given regex"""

        column_name = column.name
        message = message or "invalid {}".format(column_name)

        for index, row in enumerate(self.rows, start=1):
            text = row[column_name]
            try:
                column.validator(text)
            except ValidationError:
                error = "{} : {}".format(message, text)
                self.add_error([index], column_name, error)

    def check_unique(self, column, message=None):
        """Check that all the values of the given column are unique"""

        unique = {}
        column_name = column.name
        message = message or "%s is not unique" % column_name

        for index, row in enumerate(self.rows, start=1):
            text = row[column_name]
            lines = unique.setdefault(text, [])
            lines.append(index)
            if len(lines) > 1:
                error = "{} : {}".format(message, text)
                self.add_error(lines, column_name, error)

    def add_error(self, lines, column_name, message):
        """Add the given error to the list of errors"""

        for line in lines:
            error = VeritasError(line=line, column_name=column_name, message=message)
            self.errors.append(error)

    def print_errors(self):
        """Prints the errors"""

        for error in self.errors:
            print(error.message)


class VeritasColumn:
    """A VeritasColumn represents a column in the CSV file"""

    def __init__(self, column_name, validator, is_unique):

        self.name = column_name
        # the validator used to validate the values in the column
        self.validator = validator
        # should all the values be unique in the column?
        self.is_unique = is_unique


class VeritasError:
    """An error in the CVS file"""

    def __init__(self, line, column_name, message):
        """Constructor"""

        self.line = line
        self.column_name = column_name
        self.message = "Error line %s for column %s : %s" % (line, column_name, message)
