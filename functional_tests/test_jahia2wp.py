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
EXTRA_CONFIG_YAML = os.path.join(SRC_DIR_PATH, '..', 'functional_tests', 'extra.yaml')
# For 'generate-many' tests
SITE_URL_MANY = "https://{0}/test_many".format(TEST_HOST)
CSV_FILE_MANY = os.path.join(SRC_DIR_PATH, '..', 'functional_tests', 'one_site.csv')
# Must be identical as WP_ENV defined on Travis-ci and also be reported in CSV_FILE_MANY file
OPENSHIFT_ENV_MANY = 'test'


@pytest.fixture(scope="module")
def setup():
    # clean WP site
    wp_generator = MockedWPGenerator({'openshift_env': OPENSHIFT_ENV, 'wp_site_url': SITE_URL_SPECIFIC})
    wp_generator.clean()
    # clean backups
    backup_path = WPBackup(OPENSHIFT_ENV, SITE_URL_SPECIFIC).path
    shutil.rmtree(backup_path, ignore_errors=True)


class TestCommandLine:

    # ORDER matters

    def test_check_one_fails(self, setup):
        assert not Utils.run_command('python {} check {} {}'.format(
                                     SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))

    def test_clean_one_fails(self):
        assert not Utils.run_command('python {} clean {} {} --stop-on-errors'.format(
                                     SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))

    def test_generate_one_success(self):
        expected = "Successfully created new WordPress site at {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python {} generate {} {} --extra-config={}'.format(
                                 SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC, EXTRA_CONFIG_YAML)) == expected

    def test_update_plugins_one_success(self):
        expected = "Successfully updated WordPress plugin list at {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python {} update-plugins {} {}'.format(
                                 SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_backup_full(self):
        expected = "Successfull full backup for {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python {} backup {} {}'.format(
                                 SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_backup_incremental(self):
        expected = "Successfull inc backup for {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python {} backup {} {}'.format(
                                 SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_list_plugins(self):
        expected = "Plugin list for site '"
        assert Utils.run_command('python {} list-plugins {} {} --extra-config={}'.format(
                                 SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC,
                                 EXTRA_CONFIG_YAML)).startswith(expected)

    def test_generate_one_fails(self):
        assert not Utils.run_command('python {} generate {} {} --extra-config={}'.format(
                                     SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC, EXTRA_CONFIG_YAML))

    def test_check_one_success(self):
        expected = "WordPress site valid and accessible at {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python {} check {} {}'.format(
                                 SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_wp_admins(self):
        user = WPUser(
            Utils.get_mandatory_env(key="WP_ADMIN_USER"),
            Utils.get_mandatory_env(key="WP_ADMIN_EMAIL"),
            role='administrator')
        expected = repr(user)
        assert Utils.run_command('python {} admins {} {}'.format(
                                 SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_inventory(self):

        version = Utils.run_command('python {} version {} {}'.format(
                                    SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))

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

    def test_generate_many_success(self):
        assert Utils.run_command('python {} generate-many {}'.format(SCRIPT_FILE, CSV_FILE_MANY))

    def test_clean_one(self):
        assert Utils.run_command('python {} clean {} {} --stop-on-errors'.format(
                                 SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))
        assert Utils.run_command('python {} clean {} {} --stop-on-errors'.format(
                                 SCRIPT_FILE, OPENSHIFT_ENV_MANY, SITE_URL_MANY))
