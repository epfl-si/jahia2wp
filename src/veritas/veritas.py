""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017 """

from django.core.validators import URLValidator, ValidationError

from utils import Utils
from .validators import validate_string, validate_yes_or_no, \
    validate_openshift_env, validate_site_type, validate_theme, validate_theme_faculty, validate_languages, \
    validate_unit, mock_validate_unit

BASE_COLUMNS = [
    ("wp_site_url", URLValidator(schemes=['https']), True),
    ("wp_site_title", validate_string, False),
    ("wp_tagline", validate_string, False),
    ("site_type", validate_site_type, False),
    ("openshift_env", validate_openshift_env, False),
    # category => no validation
    ("theme", validate_theme, False),
    ("theme_faculty", validate_theme_faculty, False),
    # status => no validation
    ("installs_locked", validate_yes_or_no, False),
    ("updates_automatic", validate_yes_or_no, False),
    ("langs", validate_languages, False),
    # comment => no validation
]

if Utils.get_optional_env('TRAVIS', False):

    JAHIA2WP_COLUMNS = BASE_COLUMNS + [
        ("unit_name", mock_validate_unit, False),
    ]
else:
    JAHIA2WP_COLUMNS = BASE_COLUMNS + [
        ("unit_name", validate_unit, False),
    ]


MOCK_JAHIA2WP_COLUMNS = BASE_COLUMNS + [
    ("unit_name", mock_validate_unit, False),
]


class VeritasValidor:
    """
    Validates a CSV file containing a list of WordPress metadata
    You can use https://regex101.com/ to validate your regex
    """

    # the csv delimiter
    DELIMITER = ","

    @classmethod
    def filter_valid_rows(cls, csv_file, columns=JAHIA2WP_COLUMNS):
        """Shortcut method to call get_valid_rows, print errors, and only return valid elements"""
        # use Veritas to get valid rows
        validator = cls(csv_file, columns)
        rows = validator.get_valid_rows()

        # print errors
        if validator.errors:
            print("The following lines have errors and have been filtered out:\n")
            validator.print_errors()

        # return valid rows only
        return rows

    def __init__(self, csv_path, columns=JAHIA2WP_COLUMNS):
        """ csv_path: path on file system pointing the CSV file to validate
            columns: description of the validations to make on columns, array of tuple
                [(column_name, validator, is_unique), (), ()]
        """

        self.csv_path = csv_path

        # the rows
        self.rows = []

        # the VeritasErrors
        self.errors = []

        # the VeritasColumns
        self.columns = []

        # define the columns
        for name, validator, is_unique in columns:
            self.columns.append(VeritasColumn(name, validator, is_unique))

        # load the rows
        self.rows = Utils.csv_filepath_to_dict(file_path=self.csv_path, delimiter=self.DELIMITER)

    def validate(self):
        """Validate the columns

        Return
        True -> no errors
        False -> errors
        """

        # check the regexp
        for column in self.columns:
            self._check_validators(column)

        # check the uniqueness
        for column in self.columns:
            if column.is_unique:
                self._check_unique(column)

        # sort the errors by the line number
        self.errors.sort(key=lambda x: x.line)

        return not self.errors

    def print_errors(self):
        """Prints the errors"""

        for error in self.errors:
            print(error.message)

    def get_valid_rows(self):
        """Return the content of the CSV file, less the lines which have an error"""
        # initialize errors and run validation
        self.errors = []
        self.validate()

        # local function to filter out lines with errors
        lines_with_errors = set([error.line for error in self.errors])

        def _is_valid(item):
            index, row = item
            return index not in lines_with_errors

        # return valid rows
        return tuple(filter(_is_valid, enumerate(self.rows)))

    def _check_validators(self, column, message=None):
        """Check all the given column values with the given regex"""

        column_name = column.name
        message = message or "invalid {}".format(column_name)

        for index, row in enumerate(self.rows, start=1):
            text = row[column_name]
            try:
                column.validator(text)
            except ValidationError:
                error = "{} : {}".format(message, text)
                self._add_error([index], column_name, error)

    def _check_unique(self, column, message=None):
        """Check that all the values of the given column are unique"""

        unique = {}
        column_name = column.name
        message = message or "{} is not unique".format(column_name)

        for index, row in enumerate(self.rows, start=1):
            text = row[column_name]
            lines = unique.setdefault(text, [])
            lines.append(index)
            if len(lines) > 1:
                error = "{} : {}".format(message, text)
                self._add_error(lines, column_name, error)

    def _add_error(self, lines, column_name, message):
        """Add the given error to the list of errors"""

        for line in lines:
            error = VeritasError(line=line, column_name=column_name, message=message)
            self.errors.append(error)


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
        self.message = "Error line {} for column {} : {}".format(line, column_name, message)
