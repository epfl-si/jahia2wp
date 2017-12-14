"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os
import shutil

import pytest

from settings import OPENSHIFT_ENV, SRC_DIR_PATH
from utils import Utils
from wordpress import WPUser, WPBackup
from wordpress.generator import MockedWPGenerator

SCRIPT_FILE = os.path.join(SRC_DIR_PATH, 'jahia2wp.py')
TEST_HOST = 'localhost'
TEST_SITE = 'unittest'
SITE_URL_SPECIFIC = "https://{0}/{1}".format(TEST_HOST, TEST_SITE)


@pytest.fixture(scope="module")
def setup():
    # clean WP site
    wp_env = OPENSHIFT_ENV
    wp_url = SITE_URL_SPECIFIC
    wp_generator = MockedWPGenerator(wp_env, wp_url)
    wp_generator.clean()
    # clean backups
    backup_path = WPBackup(wp_env, wp_url).path
    shutil.rmtree(backup_path, ignore_errors=True)


class TestCommandLine:

    # ORDER matters

    def test_check_one_fails(self, setup):
        assert not Utils.run_command('python %s check %s %s'
                                     % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))

    def test_clean_one_fails(self):
        assert not Utils.run_command('python %s clean %s %s --stop-on-errors'
                                     % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))

    def test_generate_one_success(self):
        expected = "Successfully created new WordPress site at {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s generate %s %s'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_backup_full(self):
        expected = "Successfull full backup for {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s backup %s %s'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_backup_incremental(self):
        expected = "Successfull inc backup for {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s backup %s %s'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_list_plugins(self):
        expected = "Plugin list for site '"
        assert Utils.run_command('python %s list-plugins %s %s'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)).startswith(expected)

    def test_generate_one_fails(self):
        assert not Utils.run_command('python %s generate %s %s'
                                     % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))

    def test_check_one_success(self):
        expected = "WordPress site valid and accessible at {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s check %s %s'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_wp_admins(self):
        user = WPUser(
            Utils.get_mandatory_env(key="WP_ADMIN_USER"),
            Utils.get_mandatory_env(key="WP_ADMIN_EMAIL"),
            role='administrator')
        expected = repr(user)
        assert Utils.run_command('python %s admins %s %s'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_inventory(self):

        version = Utils.run_command('python %s version %s %s' % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))

        expected_lines = [
            "path;valid;url;version;db_name;db_user;admins",
            "/srv/{0}/{1}/htdocs/{2};ok;{3};{4};wp_".format(
                OPENSHIFT_ENV, TEST_HOST, TEST_SITE, SITE_URL_SPECIFIC, version),
        ]

        output = Utils.run_command(
            'python {0} inventory /srv/{1}/{2}'.format(
                SCRIPT_FILE, OPENSHIFT_ENV, TEST_HOST))

        for expected_line in expected_lines:
            assert expected_line in output

    def test_clean_one(self):
        assert Utils.run_command('python %s clean %s %s --stop-on-errors'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))
