"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os
import pytest

from utils import Utils
from wordpress import WPGenerator, WPUser


CURRENT_DIR = os.path.dirname(__file__)
SRC_DIR = os.path.abspath(os.path.join(CURRENT_DIR, '..'))
SCRIPT_FILE = os.path.join(SRC_DIR, 'jahia2wp.py')

TEST_ENV = 'test'


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
        assert not Utils.run_command('python %s check %s http://localhost/unittest'
                                     % (SCRIPT_FILE, TEST_ENV))

    def test_clean_one_fails(self):
        assert not Utils.run_command('python %s clean %s http://localhost/unittest'
                                     % (SCRIPT_FILE, TEST_ENV))

    def test_generate_one_success(self):
        expected = "Successfully created new WordPress site at http://localhost/unittest"
        assert Utils.run_command('python %s generate %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_backup_full(self):
        expected = "Successfully backed-up WordPress site for http://localhost/unittest"
        assert Utils.run_command('python %s backup %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_backup_incremental(self):
        expected = "Successfully backed-up WordPress site for http://localhost/unittest"
        assert Utils.run_command('python %s backup %s http://localhost/unittest --backup-type=inc'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_deprecated_calls(self):
        expected = "WARNING: Call to deprecated function"
        assert Utils.run_command('python %s check-one %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)).startswith(expected)

    def test_generate_one_fails(self):
        assert not Utils.run_command('python %s generate %s http://localhost/unittest'
                                     % (SCRIPT_FILE, TEST_ENV))

    def test_check_one_success(self):
        expected = "WordPress site valid and accessible at http://localhost/unittest"
        assert Utils.run_command('python %s check %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_wp_version(self):
        expected = Utils.get_mandatory_env(key="WP_VERSION")
        assert Utils.run_command('python %s version %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_wp_admins(self):
        user = WPUser(
            Utils.get_mandatory_env(key="WP_ADMIN_USER"),
            Utils.get_mandatory_env(key="WP_ADMIN_EMAIL"),
            role='administrator')
        expected = repr(user)
        assert Utils.run_command('python %s admins %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV)) == expected

    def test_inventory(self):
        expected = """path;valid;url;version;db_name;db_user;admins
/srv/test/localhost/htdocs/;KO;;;;;
/srv/test/localhost/htdocs/unittest;ok;http://localhost/unittest;4.8;wp_"""
        assert Utils.run_command('python %s inventory %s /srv/test/localhost'
                                 % (SCRIPT_FILE, TEST_ENV)).startswith(expected)

    def test_clean_one(self):
        assert Utils.run_command('python %s clean %s http://localhost/unittest'
                                 % (SCRIPT_FILE, TEST_ENV))

    def test_veritas(self):
        from veritas.tests.test_veritas import CURRENT_DIR, TEST_FILE
        filename = os.path.join(CURRENT_DIR, TEST_FILE)
        assert Utils.run_command('python %s veritas %s' % (SCRIPT_FILE, filename))
