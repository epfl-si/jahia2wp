# pylint: disable=W1306
import os
import logging
import subprocess
from urllib.parse import urlparse

from epflldap.ldap_search import get_username, get_email

from utils import Utils


class WPSite:
    """ Pure python object that will define a WP site by its path & url
        its title is optionnal, just to provide a default value to the final user
    """

    PROTOCOL = "http"
    DEFAULT_TITLE = "New WordPress"
    WP_VERSION = Utils.get_mandatory_env(key="WP_VERSION")

    def __init__(self, openshift_env, wp_site_url, wp_default_site_title=None):
        # extract domain and folder from given url
        url = urlparse(wp_site_url)

        # TODO: use validators from veritas to validate openshift_env
        self.openshift_env = openshift_env

        # set WP informations
        self.domain = url.netloc.strip('/')
        self.folder = url.path.strip('/')
        self.wp_default_site_title = wp_default_site_title or self.DEFAULT_TITLE

    def __repr__(self):
        return "WP@{}/{}/{}".format(self.openshift_env, self.domain, self.folder)

    @property
    def path(self):
        return "/srv/{0.openshift_env}/{0.domain}/htdocs/{0.folder}".format(self)

    @property
    def url(self):
        return "{0.PROTOCOL}://{0.domain}/{0.folder}".format(self)


class WPUser:
    """ Pure python object that will define a WP user by its name and email.
        its password can be defined or generated
    """

    WP_PASSWORD_LENGTH = 32

    def __init__(self, username, email, password=None):
        # TODO: use validators from veritas to validate both username and email
        self.username = username
        self.email = email
        self.password = password

    def __repr__(self):
        password_string = 'xxxx' if self.password is not None else 'None'
        return "{0.username}:{0.email} <{1}>".format(self, password_string)

    @classmethod
    def from_sciper(cls, sciper_id):
        return cls(
            username=get_username(sciper=sciper_id),
            email=get_email(sciper=sciper_id)
        )

    def set_password(self, password=None):
        self.password = password or Utils.generate_password(self.WP_PASSWORD_LENGTH)


class WPRawConfig:
    """ First object to implement some business logic
        - is the site installed? properly configured ?

        It provides also the methods to actually interact with WP-CLI
        - generic run_wp_cli
        - adding WP users, either from name+email or sciperID
    """

    def __init__(self, wordpress):
        self.wordpress = wordpress

    def __repr__(self):
        installed_string = '[ok]' if self.is_installed else '[ko]'
        return "config {0} for {1}".format(installed_string, repr(self.wordpress))

    def run_wp_cli(self, command):
        try:
            cmd = "wp {} --path='{}'".format(command, self.wordpress.path)
            logging.debug("exec '%s'", cmd)
            return subprocess.check_output(cmd, shell=True)
        except subprocess.CalledProcessError as err:
            logging.error("%s - WP export - wp_cli failed : %s", repr(self.wordpress), err)
            return None

    @property
    def is_installed(self):
        return os.path.isdir(self.wordpress.path)

    @property
    def is_config_valid(self):
        if not self.is_installed:
            return False
        # TODO: check that the config is working (DB and user ok)
        # wp-cli command (status?)

    @property
    def is_install_valid(self):
        if not self.is_config_valid():
            return False
        # TODO: check that the site is available, that user can login and upload media
        # tests from test_wordpress

    def add_wp_user(self, username, email):
        return self._add_user(WPUser(username, email))

    def add_ldap_user(self, sciper_id):
        return self._add_user(WPUser.from_sciper(sciper_id))

    def _add_user(self, user):
        if not user.password:
            user.set_password()
        # TODO: call wp-cli to add user in WP
        return user


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

    def __init__(self, openshift_env, wp_site_url, wp_default_site_title, owner_id, responsible_id):
        # create WordPress site and config
        self.wp_site = WPSite(openshift_env, wp_site_url, wp_default_site_title)
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
        return "generator for {}".format(repr(self.wordpress))

    def generate(self):
        # create specific mysql db and user
        self.prepare_db()

        # download, config and install WP
        self.install_wp()

        # add 2 given webmasters
        self.add_webmasters()

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

    def prepare_db(self):
        # create htdocs path
        self.run_command("mkdir -p /srv/{0.openshift_env}/{0.domain}/htdocs/{0.folder}".format(self))

        # create MySQL user
        command = "-e \"CREATE USER '{0.mysql_wp_user}' IDENTIFIED BY '{0.mysql_wp_password}';\""
        self.run_mysql(command.format(self))

        # grant privileges
        command = "-e \"GRANT ALL PRIVILEGES ON \`{0.wp_db_name}\`.* TO \`{0.mysql_wp_user}\`@'%';\""
        self.run_mysql(command.format(self))

    def install_wp(self):
        # check we have a clean place first
        if self.wp_config.is_installed:
            logging.error("%s - WP export - wordpress files already found", repr(self))
            return False

        # install WordPress 4.8
        self.run_wp_cli("core download --version=4.8")

        # config WordPress
        command = "config create --dbname='{0.wp_db_name}' --dbuser='{0.mysql_wp_user}'" \
            " --dbpass='{0.mysql_wp_password}' --dbhost={0.MYSQL_DB_HOST}"
        self.run_wp_cli(command.format(self))

        # create database
        self.run_wp_cli("db create --path=/srv/{0.openshift_env}/{0.domain}/htdocs".format(self))

        # fill out first form in install process (setting admin user and permissions)
        command = "--allow-root core install --url={0.url} --title='{0.wp_default_site_title}'" \
            " --admin_user={1.username} --admin_password='{1.password}'"\
            " --admin_email='{1.email}'"
        self.run_wp_cli(command.format(self, self.wp_admin))

    def add_webmasters(self):
        owner = self.wp_config.add_ldap_user(self.owner_id)
        responsible = self.wp_config.add_ldap_user(self.responsible_id)
        return (owner, responsible)


class MockedWPGenerator(WPGenerator):

    def add_webmasters(self):
        owner = self.wp_config.add_wp_user("owner", "owner@epfl.ch")
        responsible = self.wp_config.add_wp_user("responsible", "responsible@epfl.ch")
        return (owner, responsible)
