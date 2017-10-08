"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os
import pytest

from utils import Utils
from wordpress import WPGenerator, WPUser


CURRENT_DIR = os.path.dirname(__file__)
TEST_FILE = 'csv_fixture.csv'

SRC_DIR = os.path.abspath(os.path.join(CURRENT_DIR, '..'))
SCRIPT_FILE = os.path.join(SRC_DIR, 'jahia2wp.py')

TEST_ENV = 'test'

EXPECTED_OUTPUT_FROM_CSV = [
        {'key': 'table_prefix', 'value': 'wp_', 'type': 'variable'},
        {'key': 'DB_NAME', 'value': 'wp_a0veseethknlxrhdaachaj5qgdixh', 'type': 'constant'},
        {'key': 'DB_USER', 'value': 'ogtc,62msegz2beji', 'type': 'constant'},
        {'key': 'DB_PASSWORD', 'value': 'Rfcua2LKD^vpGy@m*R*Z', 'type': 'constant'},
        {'key': 'DB_COLLATE', 'value': '', 'type': 'constant'}
    ]


class TestUtils:

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


@pytest.fixture(scope="module")
def setup():
    wp_env = TEST_ENV
    wp_url = 'http://localhost/unittest'
    wp_generator = WPGenerator(wp_env, wp_url)
    if wp_generator.wp_config.is_installed:
        wp_generator.clean()


class TestCommandLine:

    # ORDER matters

    def test_check_one_fails(self, setup):
        assert not Utils.run_command('python %s check-one %s http://localhost/unittest'
                                     % (SCRIPT_FILE, TEST_ENV))

    def test_clean_one_fails(self):
        assert not Utils.run_command('python %s clean-one %s http://localhost/unittest'
                                     % (SCRIPT_FILE, TEST_ENV))

    def test_generate_one_success(self):
        expected = "Successfully created new WordPress site at http://localhost/unittest"
        assert Utils.run_command('python %s generate-one %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_generate_one_fails(self):
        assert not Utils.run_command('python %s generate-one %s http://localhost/unittest'
                                     % (SCRIPT_FILE, TEST_ENV))

    def test_check_one_success(self):
        expected = "WordPress site valid and accessible at http://localhost/unittest"
        assert Utils.run_command('python %s check-one %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_wp_version(self):
        expected = Utils.get_mandatory_env(key="WP_VERSION")
        assert Utils.run_command('python %s wp-version %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_wp_admins(self):
        user = WPUser(
            Utils.get_mandatory_env(key="WP_ADMIN_USER"),
            Utils.get_mandatory_env(key="WP_ADMIN_EMAIL"),
            role='administrator')
        expected = repr(user)
        assert Utils.run_command('python %s wp-admins %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_inventory(self):
        expected = """path;valid;url;version;db_name;db_user;admins
/srv/test/localhost/htdocs/;KO;;;;;
/srv/test/localhost/htdocs/unittest;ok;http://localhost/unittest;4.8;wp_"""
        assert Utils.run_command('python %s inventory %s /srv/test/localhost'
                                 % (SCRIPT_FILE, TEST_ENV)).startswith(expected)

    def test_clean_one(self):
        assert Utils.run_command('python %s clean-one %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV))
