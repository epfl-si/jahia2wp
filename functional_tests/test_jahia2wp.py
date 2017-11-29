"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os

import pytest

from settings import DOCKER_IP, OPENSHIFT_ENV, TEST_SITE, SRC_DIR_PATH
from utils import Utils
from wordpress import WPUser
from wordpress.generator import MockedWPGenerator

SCRIPT_FILE = os.path.join(SRC_DIR_PATH, 'jahia2wp.py')
SITE_URL_SPECIFIC = "http://{0}/{1}".format(DOCKER_IP, TEST_SITE)


@pytest.fixture(scope="module")
def setup():
    wp_env = OPENSHIFT_ENV
    wp_url = SITE_URL_SPECIFIC
    wp_generator = MockedWPGenerator(wp_env, wp_url)
    if wp_generator.wp_config.is_installed:
        wp_generator.clean()


class TestCommandLine:

    # ORDER matters

    def test_check_one_fails(self, setup):
        assert not Utils.run_command('python %s check %s %s'
                                     % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))

    def test_clean_one_fails(self):
        assert not Utils.run_command('python %s clean %s %s'
                                     % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))

    def test_generate_one_success(self):
        expected = "Successfully created new WordPress site at {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s generate %s %s'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_backup_full(self):
        expected = "Successfully backed-up WordPress site for {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s backup %s %s'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC)) == expected

    def test_backup_incremental(self):
        expected = "Successfully backed-up WordPress site for {}".format(SITE_URL_SPECIFIC)
        assert Utils.run_command('python %s backup %s %s --backup-type=inc'
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

    def test_wp_version(self):
        expected = Utils.get_mandatory_env(key="WP_VERSION")

        # we do the check only if a specific version was given
        # (e.g. "4.9"), because "latest" will depend on when
        # the test is run
        if expected != "latest":
            assert Utils.run_command('python %s version %s %s'
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
                OPENSHIFT_ENV, DOCKER_IP, TEST_SITE, SITE_URL_SPECIFIC, version),
        ]

        output = Utils.run_command(
            'python {0} inventory {1} /srv/{1}/{2}'.format(
                SCRIPT_FILE, OPENSHIFT_ENV, DOCKER_IP))

        for expected_line in expected_lines:
            assert expected_line in output

    def test_clean_one(self):
        assert Utils.run_command('python %s clean %s %s'
                                 % (SCRIPT_FILE, OPENSHIFT_ENV, SITE_URL_SPECIFIC))
