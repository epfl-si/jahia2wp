# pylint: disable=W1306
import shutil
import logging
import subprocess

from utils import Utils

from .models import WPSite, WPUser
from .configurator import WPRawConfig


class WPGenerator:
    """ High level object to entirely setup a WP sites with some users.

        It makes use of the lower level object (WPSite, WPUser, WPRawConfig)
        and provides methods to access and control the DB
    """

    DB_NAME_LENGTH = 32
    MYSQL_USER_NAME_LENGTH = 32
    MYSQL_PASSWORD_LENGTH = 32

    MYSQL_DB_HOST = Utils.get_mandatory_env(key="MYSQL_DB_HOST")
    MYSQL_SUPER_USER = Utils.get_mandatory_env(key="MYSQL_SUPER_USER")
    MYSQL_SUPER_PASSWORD = Utils.get_mandatory_env(key="MYSQL_SUPER_PASSWORD")

    WP_ADMIN_USER = Utils.get_mandatory_env(key="WP_ADMIN_USER")
    WP_ADMIN_EMAIL = Utils.get_mandatory_env(key="WP_ADMIN_EMAIL")

    def __init__(self, openshift_env, wp_site_url, wp_default_site_title=None, owner_id=None, responsible_id=None):
        # create WordPress site and config
        self.wp_site = WPSite(openshift_env, wp_site_url, wp_default_site_title=wp_default_site_title)
        self.wp_config = WPRawConfig(self.wp_site)

        # prepare admin for exploitation/maintenance
        self.wp_admin = WPUser(self.WP_ADMIN_USER, self.WP_ADMIN_EMAIL)
        self.wp_admin.set_password()

        # store scipers_id for later
        self.owner_id = owner_id
        self.responsible_id = responsible_id

        # create mysql credentials
        self.wp_db_name = Utils.generate_random_b64(self.DB_NAME_LENGTH).lower()
        self.mysql_wp_user = Utils.generate_random_b64(self.MYSQL_USER_NAME_LENGTH).lower()
        self.mysql_wp_password = Utils.generate_password(self.MYSQL_PASSWORD_LENGTH)

    def __repr__(self):
        return "generator for {}".format(repr(self.wp_site))

    def run_command(self, command):
        try:
            subprocess.check_output(command, shell=True)
            logging.debug("Generator - %s - Run command %s", repr(self), command)
        except subprocess.CalledProcessError as err:
            logging.error("Generator - %s - Command %s failed %s", repr(self), command, err)
            return False

    def run_mysql(self, command):
        mysql_connection_string = "mysql -h {0.MYSQL_DB_HOST} -u {0.MYSQL_SUPER_USER}" \
            " --password={0.MYSQL_SUPER_PASSWORD} ".format(self)
        self.run_command(mysql_connection_string + command)

    def run_wp_cli(self, command):
        return self.wp_config.run_wp_cli(command)

    def generate(self):
        # check we have a clean place first
        if self.wp_config.is_installed:
            logging.error("%s - WP export - wordpress files already found", repr(self))
            return False

        # create specific mysql db and user
        self.prepare_db()

        # download, config and install WP
        self.install_wp()

        # add 2 given webmasters
        self.add_webmasters()

    def prepare_db(self):
        # create htdocs path
        self.run_command("mkdir -p {}".format(self.wp_site.path))

        # create MySQL user
        command = "-e \"CREATE USER '{0.mysql_wp_user}' IDENTIFIED BY '{0.mysql_wp_password}';\""
        self.run_mysql(command.format(self))

        # grant privileges
        command = "-e \"GRANT ALL PRIVILEGES ON \`{0.wp_db_name}\`.* TO \`{0.mysql_wp_user}\`@'%';\""
        self.run_mysql(command.format(self))

    def install_wp(self):
        # install WordPress
        self.run_wp_cli("core download --version={}".format(self.wp_site.WP_VERSION))

        # config WordPress
        command = "config create --dbname='{0.wp_db_name}' --dbuser='{0.mysql_wp_user}'" \
            " --dbpass='{0.mysql_wp_password}' --dbhost={0.MYSQL_DB_HOST}"
        self.run_wp_cli(command.format(self))

        # create database
        self.run_wp_cli("db create")

        # fill out first form in install process (setting admin user and permissions)
        command = "--allow-root core install --url={0.url} --title='{0.wp_default_site_title}'" \
            " --admin_user={1.username} --admin_password='{1.password}'"\
            " --admin_email='{1.email}'"
        self.run_wp_cli(command.format(self.wp_site, self.wp_admin))

    def add_webmasters(self):
        if self.owner_id is not None:
            owner = self.wp_config.add_ldap_user(self.owner_id)
            if owner is not None:
                logging.info("%s - WP config - added owner %s", self.wp_site.path, owner.username)
        if self.responsible_id is not None:
            responsible = self.wp_config.add_ldap_user(self.responsible_id)
            if responsible is not None:
                logging.info("%s - WP config - added responsible %s", self.wp_site.path, responsible.username)

    def clean(self):
        # TODO: retrieve db_infos (db_name, mysql_username, mysql_password)
        # TODO: clean db
        # TODO: clean files
        logging.debug("%s - WP config - removing files", self.wp_site.path)
        shutil.rmtree(self.wp_site.path)


class MockedWPGenerator(WPGenerator):

    def add_webmasters(self):
        owner = self.wp_config.add_wp_user("owner", "owner@epfl.ch")
        responsible = self.wp_config.add_wp_user("responsible", "responsible@epfl.ch")
        return (owner, responsible)
