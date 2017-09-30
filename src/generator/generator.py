# pylint: disable=W1306
import os
import logging
import subprocess
from urllib.parse import urlparse

from epflldap.ldap_search import get_username, get_email

from utils import Utils


class WPGenerator:

    USER_NAME_LENGTH = 32
    DB_NAME_LENGTH = 32
    PASSWORD_LENGTH = 32
    PROTOCOL = "http"

    MYSQL_DB_HOST = Utils.get_mandatory_env(key="MYSQL_DB_HOST")
    MYSQL_SUPER_USER = Utils.get_mandatory_env(key="MYSQL_SUPER_USER")
    MYSQL_SUPER_PASSWORD = Utils.get_mandatory_env(key="MYSQL_SUPER_PASSWORD")

    WP_VERSION = Utils.get_mandatory_env(key="WP_VERSION")
    WP_ADMIN_USER = Utils.get_mandatory_env(key="WP_ADMIN_USER")
    WP_ADMIN_EMAIL = Utils.get_mandatory_env(key="WP_ADMIN_EMAIL")

    def __init__(self,
                 openshift_env=None,
                 wp_site_url=None,
                 wp_default_site_title=None,
                 owner_id=None,
                 responsible_id=None):

        url = urlparse(wp_site_url)

        self.openshift_env = openshift_env
        self.domain = url.netloc.strip('/')
        self.folder = url.path.strip('/')
        self.wp_default_site_title = wp_default_site_title
        self.set_users_info(owner_id, responsible_id)
        self.set_unique_vars()

    def __repr__(self):
        return "{}/{}/{}".format(self.openshift_env, self.domain, self.folder)

    def set_users_info(self, owner_id, responsible_id):
        self.owner_username = get_username(sciper=owner_id)
        self.owner_email = get_email(sciper=owner_id)
        self.responsible_username = get_username(sciper=responsible_id)
        self.responsible_email = get_email(sciper=responsible_id)

    def set_unique_vars(self):
        self.mysql_wp_user = Utils.generate_random_b64(self.USER_NAME_LENGTH).lower()
        self.mysql_wp_password = Utils.generate_password(self.PASSWORD_LENGTH)
        self.wp_db_name = Utils.generate_random_b64(self.DB_NAME_LENGTH).lower()
        self.wp_admin_password = Utils.generate_password(self.PASSWORD_LENGTH)
        self.wp_webmaster_password = Utils.generate_password(self.PASSWORD_LENGTH)
        self.wp_responsible_password = Utils.generate_password(self.PASSWORD_LENGTH)

    @property
    def path(self):
        return "/srv/{0.openshift_env}/{0.domain}/htdocs/{0.folder}".format(self)

    @property
    def url(self):
        return "{0.PROTOCOL}://{0.domain}/{0.folder}".format(self)

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
        try:
            cmd = "wp {} --path='{}'".format(command, self.path)
            logging.debug("exec '%s'", cmd)
            return subprocess.check_output(cmd, shell=True)
        except subprocess.CalledProcessError as err:
            logging.error("%s - WP export - wp_cli failed : %s",
                          repr(self), err)
            return None

    def is_installed(self):
        return os.path.isdir(self.path)

    def is_config_valid(self):
        if not self.is_installed():
            return False
        # TODO EB: check that the config is working (DB and user ok)
        # wp-cli command (status?)

    def is_install_valid(self):
        if not self.is_config_valid():
            return False
        # TODO EB : check that the site is available, that user can login and upload media
        # tests from test_wordpress

    def generate(self):
        # check we have a clean place first
        if self.is_installed():
            logging.error("%s - WP export - wordpress files already found", repr(self))
            return False

        # create htdocs path
        self.run_command("mkdir -p /srv/{0.openshift_env}/{0.domain}/htdocs/{0.folder}".format(self))

        # create MySQL user
        command = "-e \"CREATE USER '{0.mysql_wp_user}' IDENTIFIED BY '{0.mysql_wp_password}';\""
        self.run_mysql(command.format(self))

        # grant privileges
        command = "-e \"GRANT ALL PRIVILEGES ON \`{0.wp_db_name}\`.* TO \`{0.mysql_wp_user}\`@'%';\""
        self.run_mysql(command.format(self))

        # install WordPress 4.8
        self.run_wp_cli("core download --version=4.8".format(self))

        # config WordPress
        command = "config create --dbname='{0.wp_db_name}' --dbuser='{0.mysql_wp_user}'" \
            " --dbpass='{0.mysql_wp_password}' --dbhost={0.MYSQL_DB_HOST}"
        self.run_wp_cli(command.format(self))

        # create database
        self.run_wp_cli("db create --path=/srv/{0.openshift_env}/{0.domain}/htdocs".format(self))

        # fill out first form in install process (setting admin user and permissions)
        command = "--allow-root core install --url={0.url} --title='{0.wp_default_site_title}'" \
            " --admin_user={0.WP_ADMIN_USER} --admin_password='{0.wp_admin_password}'"\
            " --admin_email='{0.WP_ADMIN_EMAIL}'"
        self.run_wp_cli(command.format(self))
