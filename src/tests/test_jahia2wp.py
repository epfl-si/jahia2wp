"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os
import pytest

from settings import TEST_ENV, TEST_SITE
from utils import Utils
from wordpress import WPGenerator, WPUser


CURRENT_DIR = os.path.dirname(__file__)
SRC_DIR = os.path.abspath(os.path.join(CURRENT_DIR, '..'))
SCRIPT_FILE = os.path.join(SRC_DIR, 'jahia2wp.py')

SITE_URL_SPECIFIC = "http://localhost/{}".format(TEST_SITE)


@pytest.fixture(scope="module")
def setup():
    wp_env = TEST_ENV
    wp_url = SITE_URL_SPECIFIC
    wp_generator = WPGenerator(wp_env, wp_url)
    if wp_generator.wp_config.is_installed:
        wp_generator.clean()


class TestCommandLine:

    # ORDER matters

    def test_check_one_fails(self, setup):
        assert not Utils.run_command('python %s check %s %s'
                                     % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC))

    def test_clean_one_fails(self):
        assert not Utils.run_command('python %s clean %s %s'
                                     % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC))

    def test_generate_one_success(self):
        expected = "Successfully created new WordPress site at {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s generate %s %s'
                                 % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC)) == expected

    def test_backup_full(self):
        expected = "Successfully backed-up WordPress site for {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s backup %s %s'
                                 % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC)) == expected

    def test_backup_incremental(self):
        expected = "Successfully backed-up WordPress site for {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s backup %s %s --backup-type=inc'
                                 % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC)) == expected

    def test_list_plugins(self):
        expected = "Plugin list for site '"
        assert Utils.run_command('python %s list-plugins %s %s'
                                 % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC)).startswith(expected)

    def test_deprecated_calls(self):
        expected = "WARNING: Call to deprecated function"
        assert Utils.run_command('python %s check-one %s %s'
                                 % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC)).startswith(expected)

    def test_generate_one_fails(self):
        assert not Utils.run_command('python %s generate %s %s'
                                     % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC))

    def test_check_one_success(self):
        expected = "WordPress site valid and accessible at {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s check %s %s'
                                 % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC)) == expected

    def test_wp_version(self):
        expected = Utils.get_mandatory_env(key="WP_VERSION")

        # we do the check only if a specific version was given
        # (e.g. "4.9"), because "latest" will depend on when
        # the test is run
        if expected != "latest":
            assert Utils.run_command('python %s version %s %s'
                                     % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC)) == expected

    def test_wp_admins(self):
        user = WPUser(
            Utils.get_mandatory_env(key="WP_ADMIN_USER"),
            Utils.get_mandatory_env(key="WP_ADMIN_EMAIL"),
            role='administrator')
        expected = repr(user)
        assert Utils.run_command('python %s admins %s %s'
                                 % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC)) == expected

    def test_inventory(self):

        version = Utils.run_command('python %s version %s %s' % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC))

        expected = """path;valid;url;version;db_name;db_user;admins
/srv/test/localhost/htdocs;KO;;;;;
/srv/test/localhost/htdocs/{};ok;{};{};wp_""".format(TEST_SITE, SITE_URL_SPECIFIC, version)

        assert Utils.run_command(
            'python {0} inventory {1} /srv/test/localhost'.format(
                SCRIPT_FILE, TEST_ENV)).startswith(expected)

    def test_clean_one(self):
        assert Utils.run_command('python %s clean %s %s'
                                 % (SCRIPT_FILE, TEST_ENV, SITE_URL_SPECIFIC))

    def test_veritas(self):
        from veritas.tests.test_veritas import CURRENT_DIR, TEST_FILE
        filename = os.path.join(CURRENT_DIR, TEST_FILE)
        assert Utils.run_command('python %s veritas %s' % (SCRIPT_FILE, filename))
