"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os
import pytest

from utils import Utils

from wordpress import WPSite
from wordpress.plugins.polylang import WPPolylangConfig


CURRENT_DIR = os.path.dirname(__file__)
TEST_FILE = 'csv_fixture.csv'

EXPECTED_OUTPUT_FROM_CSV = [
        {'key': 'table_prefix', 'value': 'wp_', 'type': 'variable'},
        {'key': 'DB_NAME', 'value': 'wp_a0veseethknlxrhdaachaj5qgdixh', 'type': 'constant'},
        {'key': 'DB_USER', 'value': 'ogtc,62msegz2beji', 'type': 'constant'},
        {'key': 'DB_PASSWORD', 'value': 'Rfcua2LKD^vpGy@m*R*Z', 'type': 'constant'},
        {'key': 'DB_COLLATE', 'value': '', 'type': 'constant'}
    ]

TEST_VAR = "test-var"


@pytest.fixture()
def environment(request):
    """
    Load fake environment variables for every test
    """
    os.environ["TEST_VAR"] = TEST_VAR
    return os.environ


@pytest.fixture()
def delete_environment(request):
    """
        Delete all env. vars
    """
    if os.environ.get("TEST_VAR"):
        del os.environ["TEST_VAR"]


class TestEnvironment:

    def test_empty_env(self, delete_environment):
        """
            Delete all env. vars and check that module raise an exception on load
        """
        assert "foo" == Utils.get_optional_env("TEST_VAR", "foo")
        with pytest.raises(Exception):
            Utils.get_mandatory_env("TEST_VAR")

    def test_env(self, environment):
        """
            Check default values for JAHIA _USER and _HOST
        """
        assert "test-var" == Utils.get_optional_env("TEST_VAR", "foo")
        assert "test-var" == Utils.get_mandatory_env("TEST_VAR")


class TestCSV:

    def test_csv_from_filepath(self):
        file_path = os.path.join(CURRENT_DIR, TEST_FILE)
        assert Utils.csv_filepath_to_dict(file_path) == EXPECTED_OUTPUT_FROM_CSV

    def test_csv_from_string(self):
        text = """key,value,type
table_prefix,wp_,variable
DB_NAME,wp_a0veseethknlxrhdaachaj5qgdixh,constant
DB_USER,"ogtc,62msegz2beji",constant
DB_PASSWORD,Rfcua2LKD^vpGy@m*R*Z,constant
DB_COLLATE,,constant"""
        assert Utils.csv_string_to_dict(text) == EXPECTED_OUTPUT_FROM_CSV


class TestImport:

    def test_first_level_import(self):
        assert WPSite == Utils.import_class_from_string(
            "wordpress.WPSite")

    def test_low_level_import(self):
        assert WPPolylangConfig == Utils.import_class_from_string(
            "wordpress.plugins.polylang.WPPolylangConfig")


class TestTar:

    OUTPUT_TAR = "/tmp/test.tar"
    OUTPUT_INC = "/tmp/test.inc"

    def test_generate_tar_file(self):
        # setup
        for file_name in [self.OUTPUT_TAR, self.OUTPUT_INC]:
            if os.path.exists(file_name):
                os.remove(file_name)

        # run command
        Utils.generate_tar_file(
            self.OUTPUT_TAR,
            self.OUTPUT_INC,
            os.path.join(CURRENT_DIR, TEST_FILE)
        )

        # check output
        for file_name in [self.OUTPUT_TAR, self.OUTPUT_INC]:
            assert os.path.exists(file_name)
            os.remove(file_name)
