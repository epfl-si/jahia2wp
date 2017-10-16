# pylint: disable=W1306
import os
import shutil
import logging

from utils import Utils
from settings import WP_DIRS, WP_FILES, ADD_TO_ANY_PLUGIN

from django.core.validators import URLValidator
from veritas.validators import validate_string, validate_openshift_env, validate_integer

from .models import WPSite, WPUser
from .configurator import WPRawConfig, WPThemeConfig, WPPluginConfig


class WPGenerator:
    """ High level object to entirely setup a WP sites with some users.

        It makes use of the lower level object (WPSite, WPUser, WPRawConfig)
        and provides methods to access and control the DB
    """

    DB_NAME_LENGTH = 32
    MYSQL_USER_NAME_LENGTH = 16
    MYSQL_PASSWORD_LENGTH = 20

    MYSQL_DB_HOST = Utils.get_mandatory_env(key="MYSQL_DB_HOST")
    MYSQL_SUPER_USER = Utils.get_mandatory_env(key="MYSQL_SUPER_USER")
    MYSQL_SUPER_PASSWORD = Utils.get_mandatory_env(key="MYSQL_SUPER_PASSWORD")

    WP_ADMIN_USER = Utils.get_mandatory_env(key="WP_ADMIN_USER")
    WP_ADMIN_EMAIL = Utils.get_mandatory_env(key="WP_ADMIN_EMAIL")

    def __init__(self, openshift_env, wp_site_url,
                 wp_default_site_title=None,
                 admin_password=None,
                 owner_id=None,
                 responsible_id=None):
        # validate input
        validate_openshift_env(openshift_env)
        URLValidator()(wp_site_url)
        if wp_default_site_title is not None:
            validate_string(wp_default_site_title)
        if owner_id is not None:
            validate_integer(owner_id)
        if responsible_id is not None:
            validate_integer(responsible_id)

        # create WordPress site and config
        self.wp_site = WPSite(openshift_env, wp_site_url, wp_default_site_title=wp_default_site_title)
        self.wp_config = WPRawConfig(self.wp_site)

        # prepare admin for exploitation/maintenance
        self.wp_admin = WPUser(self.WP_ADMIN_USER, self.WP_ADMIN_EMAIL)
        self.wp_admin.set_password(password=admin_password)

        # store scipers_id for later
        self.owner_id = owner_id
        self.responsible_id = responsible_id

        # create mysql credentials
        self.wp_db_name = Utils.generate_name(self.DB_NAME_LENGTH, prefix='wp_').lower()
        self.mysql_wp_user = Utils.generate_name(self.MYSQL_USER_NAME_LENGTH).lower()
        self.mysql_wp_password = Utils.generate_password(self.MYSQL_PASSWORD_LENGTH)

    def __repr__(self):
        return repr(self.wp_site)

    def run_wp_cli(self, command):
        return self.wp_config.run_wp_cli(command)

    def run_mysql(self, command):
        mysql_connection_string = "mysql -h {0.MYSQL_DB_HOST} -u {0.MYSQL_SUPER_USER}" \
            " --password={0.MYSQL_SUPER_PASSWORD} ".format(self)
        return Utils.run_command(mysql_connection_string + command)

    def generate_plugins(self):

        # install and activate AddToAny plugin
        add_to_any_plugin = WPPluginConfig(self.wp_site, 'add-to-any')
        add_to_any_plugin.install()
        if not add_to_any_plugin.is_activate:
            logging.error("%s - could not activate WP AddToAny plugin", repr(self))
            return False
        else:
            logging.debug("%s - WP AddToAny plugin is activated", repr(self))

        # config AddToAny plugin
        add_to_any_plugin.config(config_data=ADD_TO_ANY_PLUGIN)

        # install and activate BasicAuth plugin
        basic_auth = WPPluginConfig(self.wp_site, 'wp-basic-auth')
        basic_auth.install()
        if not basic_auth.is_activate:
            logging.error("%s - could not activate WP BASIC Auth plugin", repr(self))
            return False
        else:
            logging.debug("%s - WP BASIC Auth plugin is activated", repr(self))

        # install and activate Black Studio TinyMCE widget
        black_studio_tinymce_widget = WPPluginConfig(self.wp_site, 'black-studio-tinymce-widget')
        black_studio_tinymce_widget.install()
        if not black_studio_tinymce_widget.is_activate:
            logging.error("%s - could not activate WP Black Studio TinyMCE Widget plugin", repr(self))
            return False
        else:
            logging.debug("%s - WP Black Studio TinyMCE Widget is activated", repr(self))

        # install and activate TinyMCE Advanced plugin
        tinymce_advanced = WPPluginConfig(self.wp_site, 'tinymce-advanced')
        tinymce_advanced.install()
        if not tinymce_advanced.is_activate:
            logging.error("%s - could not activate WP TinyMCE Advanced plugin", repr(self))
            return False
        else:
            logging.debug("%s - WP TinyMCE Advanced is activated", repr(self))

    def generate(self):

        # check we have a clean place first
        if self.wp_config.is_installed:
            logging.error("%s - wordpress files already found", repr(self))
            return False

        # create specific mysql db and user
        logging.info("%s - setting up DB...", repr(self))
        if not self.prepare_db():
            logging.error("%s - could not set up DB", repr(self))
            return False

        # download, config and install WP
        logging.info("%s - downloading WP...", repr(self))
        if not self.install_wp():
            logging.error("%s - could not install WP", repr(self))
            return False

        # install and configure theme (default is 'epfl')
        logging.info("%s - activating theme...", repr(self))
        theme = WPThemeConfig(self.wp_site)
        theme.install()
        if not theme.activate():
            logging.error("%s - could not activate theme", repr(self))
            return False

        # install, activate and config plugins
        self.generate_plugins()

        # add 2 given webmasters
        logging.info("%s - creating webmaster accounts...", repr(self))
        if not self.add_webmasters():
            logging.error("%s - could not add webmasters", repr(self))
            return False

        # flag success
        return True

    def prepare_db(self):
        # create htdocs path
        if not Utils.run_command("mkdir -p {}".format(self.wp_site.path)):
            logging.error("%s - could not create tree structure", repr(self))
            return False

        # create MySQL DB
        command = "-e \"CREATE DATABASE {0.wp_db_name};\""
        if not self.run_mysql(command.format(self)):
            logging.error("%s - could not create DB", repr(self))
            return False

        # create MySQL user
        command = "-e \"CREATE USER '{0.mysql_wp_user}' IDENTIFIED BY '{0.mysql_wp_password}';\""
        if not self.run_mysql(command.format(self)):
            logging.error("%s - could not create user", repr(self))
            return False

        # grant privileges
        command = "-e \"GRANT ALL PRIVILEGES ON \`{0.wp_db_name}\`.* TO \`{0.mysql_wp_user}\`@'%';\""
        if not self.run_mysql(command.format(self)):
            logging.error("%s - could not grant privileges to user", repr(self))
            return False

        # flag success by returning True
        return True

    def install_wp(self):
        # install WordPress
        if not self.run_wp_cli("core download --version={}".format(self.wp_site.WP_VERSION)):
            logging.error("%s - could not download", repr(self))
            return False

        # config WordPress
        command = "config create --dbname='{0.wp_db_name}' --dbuser='{0.mysql_wp_user}'" \
            " --dbpass='{0.mysql_wp_password}' --dbhost={0.MYSQL_DB_HOST}"
        if not self.run_wp_cli(command.format(self)):
            logging.error("%s - could not create config", repr(self))
            return False

        # fill out first form in install process (setting admin user and permissions)
        command = "--allow-root core install --url={0.url} --title='{0.wp_default_site_title}'" \
            " --admin_user={1.username} --admin_password='{1.password}'"\
            " --admin_email='{1.email}'"
        if not self.run_wp_cli(command.format(self.wp_site, self.wp_admin)):
            logging.error("%s - could not setup WP site", repr(self))
            return False

        # create main menu
        self.wp_config.create_main_menu()

        # flag success by returning True
        return True

    def add_webmasters(self):
        success = True

        if self.owner_id is not None:
            owner = self.wp_config.add_ldap_user(self.owner_id)
            if owner is not None:
                logging.info("%s - added owner %s", repr(self), owner.username)
            else:
                success = False

        if self.responsible_id is not None:
            responsible = self.wp_config.add_ldap_user(self.responsible_id)
            if responsible is not None:
                logging.info("%s - added responsible %s", repr(self), responsible.username)
            else:
                success = False

        # flag a success if at least one webmaster has been created
        return success

    def clean(self):
        # retrieve db_infos
        db_name = self.wp_config.db_name
        db_user = self.wp_config.db_user

        # clean db
        logging.info("%s - cleaning up DB", repr(self))
        if not self.run_mysql('-e "DROP DATABASE {};"'.format(db_name)):
            logging.error("%s - could not drop DATABASE %s", repr(self), db_name)

        if not self.run_mysql('-e "DROP USER {};"'.format(db_user)):
            logging.error("%s - could not drop USER %s", repr(self), db_name)

        # clean directories first
        logging.info("%s - removing files", repr(self))
        for dir_path in WP_DIRS:
            path = os.path.join(self.wp_site.path, dir_path)
            if os.path.exists(path):
                shutil.rmtree(path)

        # clean files
        for file_path in WP_FILES:
            path = os.path.join(self.wp_site.path, file_path)
            if os.path.exists(path):
                os.remove(path)


class MockedWPGenerator(WPGenerator):

    def add_webmasters(self):
        owner = self.wp_config.add_wp_user("owner", "owner@epfl.ch")
        responsible = self.wp_config.add_wp_user("responsible", "responsible@epfl.ch")
        return (owner, responsible)
