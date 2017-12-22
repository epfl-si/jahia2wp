# pylint: disable=W1306
import os
import shutil
import logging

from epflldap.ldap_search import get_unit_id

from utils import Utils
import settings

from django.core.validators import URLValidator
from veritas.validators import validate_string, validate_openshift_env, \
    validate_theme_faculty, validate_theme
from .models import WPSite, WPUser
from .config import WPConfig
from .themes import WPThemeConfig
from .plugins.models import WPPluginList
from .plugins.config import WPMuPluginConfig


class WPGenerator:
    """ High level object to entirely setup a WP sites with some users.

        It makes use of the lower level object (WPSite, WPUser, WPConfig)
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

    def __init__(self, site_params, admin_password=None):
        """
        Class constructor

        Argument keywords:
        site_params -- dict with row coming from CSV file (source of truth)
        admin_password -- (optional) Password to use for 'admin' account
        """

        self._site_params = site_params

        # Setting default values

        if 'unit_name' in self._site_params and 'unit_id' not in self._site_params:
            logging.info("WPGenerator.__init__(): Please use 'unit_id' from CSV file (now recovered from 'unit_name')")
            self._site_params['unit_id'] = self.get_the_unit_id(self._site_params['unit_name'])

        if 'wp_default_title' not in self._site_params:
            self._site_params['wp_default_title'] = None

        if self._site_params.get('installs_locked', None) is None:
            self._site_params['installs_locked'] = settings.DEFAULT_CONFIG_INSTALLS_LOCKED

        if self._site_params.get('updates_automatic', None) is None:
            self._site_params['updates_automatic'] = settings.DEFAULT_CONFIG_UPDATES_AUTOMATIC

        if self._site_params.get('theme', None) is None:
            self._site_params['theme'] = settings.DEFAULT_THEME_NAME

        if ('theme_faculty' not in self._site_params or
           ('theme_faculty' in self._site_params and self._site_params['theme_faculty'] == '')):
            self._site_params['theme_faculty'] = None

        # validate input
        self.validate_mockable_args(self._site_params['wp_site_url'])
        validate_openshift_env(self._site_params['openshift_env'])

        if self._site_params['wp_default_title'] is not None:
            validate_string(self._site_params['wp_default_title'])

        validate_theme(self._site_params['theme'])

        if self._site_params['theme_faculty'] is not None:
            validate_theme_faculty(self._site_params['theme_faculty'])

        # create WordPress site and config
        self.wp_site = WPSite(
            self._site_params['openshift_env'],
            self._site_params['wp_site_url'],
            wp_default_site_title=self._site_params['wp_default_title'])
        self.wp_config = WPConfig(
            self.wp_site,
            installs_locked=self._site_params['installs_locked'],
            updates_automatic=self._site_params['updates_automatic'])

        # prepare admin for exploitation/maintenance
        self.wp_admin = WPUser(self.WP_ADMIN_USER, self.WP_ADMIN_EMAIL)
        self.wp_admin.set_password(password=admin_password)

        # create mysql credentials
        self.wp_db_name = Utils.generate_name(self.DB_NAME_LENGTH, prefix='wp_').lower()
        self.mysql_wp_user = Utils.generate_name(self.MYSQL_USER_NAME_LENGTH).lower()
        self.mysql_wp_password = Utils.generate_password(self.MYSQL_PASSWORD_LENGTH)

    def __repr__(self):
        return repr(self.wp_site)

    def run_wp_cli(self, command):
        """
        Execute a WP-CLI command using method present in WPConfig instance.

        Argument keywords:
        command -- WP-CLI command to execute. The command doesn't have to start with "wp ".
        """
        return self.wp_config.run_wp_cli(command)

    def run_mysql(self, command):
        """
        Execute MySQL request using DB information stored in instance

        Argument keywords:
        command -- Request to execute in DB.
        """
        mysql_connection_string = "mysql -h {0.MYSQL_DB_HOST} -u {0.MYSQL_SUPER_USER}" \
            " --password={0.MYSQL_SUPER_PASSWORD} ".format(self)
        return Utils.run_command(mysql_connection_string + command)

    def list_plugins(self, with_config=False, for_plugin=None):
        """
        List plugins (and configuration) for WP site

        Keyword arguments:
        with_config -- (Bool) to specify if plugin config has to be displayed
        for_plugin -- Used only if 'with_config'=True. Allow to display only configuration for one given plugin.
        """
        logging.info("WPGenerator.list_plugins(): Add parameter for 'batch file' (YAML)")
        # Batch config file (config-lot1.yml) needs to be replaced by something clean as soon as we have "batch"
        # information in the source of trousse !
        plugin_list = WPPluginList(settings.PLUGINS_CONFIG_GENERIC_FOLDER, 'config-lot1.yml',
                                   settings.PLUGINS_CONFIG_SPECIFIC_FOLDER, self._site_params)

        return plugin_list.list_plugins(self.wp_site.name, with_config, for_plugin)

    def generate(self):
        """
        Generate a complete and fully working WordPress website
        """
        # check we have a clean place first
        if self.wp_config.is_installed:
            logging.error("{} - WordPress files already found".format(repr(self)))
            return False

        # create specific mysql db and user
        logging.info("{} - Setting up DB...".format(repr(self)))
        if not self.prepare_db():
            logging.error("{} - could not set up DB".format(repr(self)))
            return False

        # download, config and install WP
        logging.info("{} - Downloading WP...".format(repr(self)))
        if not self.install_wp():
            logging.error("{} - could not install WP".format(repr(self)))
            return False

        # install and configure theme (default is settings.DEFAULT_THEME_NAME)
        logging.info("{} - Installing all themes...".format(repr(self)))
        WPThemeConfig.install_all(self.wp_site)
        logging.info("%s - Activating theme...", repr(self))
        theme = WPThemeConfig(self.wp_site, self._site_params['theme'], self._site_params['theme_faculty'])
        if not theme.activate():
            logging.error("{} - could not activate theme".format(repr(self)))
            return False

        # install, activate and config mu-plugins
        # must be done before plugins if automatic updates are disabled
        logging.info("{} - Installing mu-plugins...".format(repr(self)))
        self.generate_mu_plugins()

        # Delete all widgets, inactive themes
        self.delete_widgets()
        self.delete_inactive_themes()
        self.delete_demo_posts()

        # install, activate and config plugins
        logging.info("{} - Installing plugins...".format(repr(self)))
        self.generate_plugins()

        # flag success
        return True

    def prepare_db(self):
        """
        Prepare the DB to store WordPress configuration.
        """
        # create htdocs path
        if not Utils.run_command("mkdir -p {}".format(self.wp_site.path)):
            logging.error("{} - could not create tree structure".format(repr(self)))
            return False

        # create MySQL DB
        command = "-e \"CREATE DATABASE {0.wp_db_name};\""
        if not self.run_mysql(command.format(self)):
            logging.error("{} - could not create DB".format(repr(self)))
            return False

        # create MySQL user
        command = "-e \"CREATE USER '{0.mysql_wp_user}' IDENTIFIED BY '{0.mysql_wp_password}';\""
        if not self.run_mysql(command.format(self)):
            logging.error("{} - could not create user".format(repr(self)))
            return False

        # grant privileges
        command = "-e \"GRANT ALL PRIVILEGES ON \`{0.wp_db_name}\`.* TO \`{0.mysql_wp_user}\`@'%';\""
        if not self.run_mysql(command.format(self)):
            logging.error("{} - could not grant privileges to user".format(repr(self)))
            return False

        # flag success by returning True
        return True

    def install_wp(self):
        """
        Execute WordPress installation
        """
        # install WordPress
        if not self.run_wp_cli("core download --version={}".format(self.wp_site.WP_VERSION)):
            logging.error("{} - could not download".format(repr(self)))
            return False

        # config WordPress
        command = "config create --dbname='{0.wp_db_name}' --dbuser='{0.mysql_wp_user}'" \
            " --dbpass='{0.mysql_wp_password}' --dbhost={0.MYSQL_DB_HOST}"
        if not self.run_wp_cli(command.format(self)):
            logging.error("{} - could not create config".format(repr(self)))
            return False

        # fill out first form in install process (setting admin user and permissions)
        command = "--allow-root core install --url={0.url} --title='{0.wp_default_site_title}'" \
            " --admin_user={1.username} --admin_password='{1.password}'"\
            " --admin_email='{1.email}'"
        if not self.run_wp_cli(command.format(self.wp_site, self.wp_admin)):
            logging.error("{} - could not setup WP site".format(repr(self)))
            return False

        # Configure permalinks
        command = "rewrite structure '/%postname%/' --hard"
        if not self.run_wp_cli(command):
            logging.error("{} - could not configure permalinks".format(repr(self)))
            return False

        # Configure TimeZone
        command = "option update timezone_string Europe/Zurich"
        if not self.run_wp_cli(command):
            logging.error("{} - could not configure time zone".format(repr(self)))
            return False

        # Configure Time Format 24H
        command = "option update time_format H:i"
        if not self.run_wp_cli(command):
            logging.error("{} - could not configure time format".format(repr(self)))
            return False

        # Configure Date Format d.m.Y
        command = "option update date_format d.m.Y"
        if not self.run_wp_cli(command):
            logging.error("{} - could not configure date format".format(repr(self)))
            return False

        # Add french for the admin interface
        command = "language core install fr_FR"
        self.run_wp_cli(command)

        # flag success by returning True
        return True

    def delete_widgets(self, sidebar="homepage-widgets"):
        """
        Delete all widgets from the given sidebar.

        There are 2 sidebars :
        - One sidebar for the homepage. In this case sidebar parameter is "homepage-widgets".
        - Another sidebar for all anothers pages. In this case sidebar parameter is "page-widgets".
        """
        cmd = "widget list {} --fields=id --format=csv".format(sidebar)
        # Result is sliced to remove 1st element which is name of field (id).
        # Because WPCLI command can take several fields, the name is displayed in the result.
        widgets_id_list = self.run_wp_cli(cmd).split("\n")[1:]
        for widget_id in widgets_id_list:
            cmd = "widget delete " + widget_id
            self.run_wp_cli(cmd)
        logging.info("All widgets deleted")

    def validate_mockable_args(self, wp_site_url):
        """ Call validators in an independant function to allow mocking them """
        URLValidator()(wp_site_url)

    def get_the_unit_id(self, unit_name):
        """
        Get unit id via LDAP Search
        """
        if unit_name is not None:
            return get_unit_id(unit_name)

    def delete_inactive_themes(self):
        """
        Delete all inactive themes
        """
        cmd = "theme list --fields=name --status=inactive --format=csv"
        themes_name_list = self.run_wp_cli(cmd).split("\n")[1:]
        for theme_name in themes_name_list:
            cmd = "theme delete {}".format(theme_name)
            self.run_wp_cli(cmd)
        logging.info("All inactive themes deleted")

    def delete_demo_posts(self):
        """
        Delete 'welcome blog' and 'sample page'
        """
        cmd = "post list --post_type=page,post --field=ID --format=csv"
        posts_list = self.run_wp_cli(cmd).split("\n")
        for post in posts_list:
            cmd = "post delete {}".format(post)
            self.run_wp_cli(cmd)
        logging.info("All demo posts deleted")

    def generate_mu_plugins(self):
        # TODO: add those plugins into the general list of plugins (with the class WPMuPluginConfig)
        WPMuPluginConfig(self.wp_site, "epfl-functions.php").install()
        WPMuPluginConfig(self.wp_site, "EPFL-SC-infoscience.php").install()
        WPMuPluginConfig(self.wp_site, "EPFL_custom_editor_menu.php").install()

        if self.wp_config.installs_locked:
            WPMuPluginConfig(self.wp_site, "EPFL_installs_locked.php").install()

        if self.wp_config.updates_automatic:
            WPMuPluginConfig(self.wp_site, "EPFL_enable_updates_automatic.php").install()
        else:
            WPMuPluginConfig(self.wp_site, "EPFL_disable_updates_automatic.php").install()

    def generate_plugins(self):
        """
        Get plugin list for WP site, install them, activate them if needed, configure them
        """
        logging.info("WPGenerator.generate_plugins(): Add parameter for 'batch file' (YAML)")
        # Batch config file (config-lot1.yml) needs to be replaced by something clean as soon as we have "batch"
        # information in the source of trousse !
        plugin_list = WPPluginList(settings.PLUGINS_CONFIG_GENERIC_FOLDER, 'config-lot1.yml',
                                   settings.PLUGINS_CONFIG_SPECIFIC_FOLDER, self._site_params)

        # Looping through plugins to install
        for plugin_name, config_dict in plugin_list.plugins(self.wp_site.name).items():

            # Fetch proper PluginConfig class (in YAML) and create instance.
            plugin_class = Utils.import_class_from_string(config_dict.config_class)
            plugin_config = plugin_class(self.wp_site, plugin_name, config_dict)

            # If we have to uninstall the plugin
            if config_dict.action == settings.PLUGIN_ACTION_UNINSTALL:
                logging.info("{} - Plugins - {}: Uninstalling...".format(repr(self), plugin_name))
                if plugin_config.is_installed:
                    plugin_config.uninstall()
                    logging.info("{} - Plugins - {}: Uninstalled!".format(repr(self), plugin_name))
                else:
                    logging.info("{} - Plugins - {}: Not installed!".format(repr(self), plugin_name))

            else:  # We have to install the plugin
                # We may have to install or do nothing (if we only want to deactivate plugin)
                if config_dict.action == settings.PLUGIN_ACTION_INSTALL:
                    logging.info("{} - Plugins - {}: Installing...".format(repr(self), plugin_name))
                    if not plugin_config.is_installed:
                        plugin_config.install()
                        logging.info("{} - Plugins - {}: Installed!".format(repr(self), plugin_name))
                    else:
                        logging.info("{} - Plugins - {}: Already installed!".format(repr(self), plugin_name))

                logging.info("{} - Plugins - {}: Setting state...".format(repr(self), plugin_name))
                plugin_config.set_state()

                if plugin_config.is_activated:
                    logging.info("{} - Plugins - {}: Activated!".format(repr(self), plugin_name))
                else:
                    logging.info("{} - Plugins - {}: Deactivated!".format(repr(self), plugin_name))

                # Configure plugin
                plugin_config.configure()

    def clean(self):
        """
        Completely clean a WordPress install, DB and files.
        """
        # retrieve db_infos
        try:
            db_name = self.wp_config.db_name
            db_user = self.wp_config.db_user

            # clean db
            logging.info("{} - cleaning up DB".format(repr(self)))
            if not self.run_mysql('-e "DROP DATABASE IF EXISTS {};"'.format(db_name)):
                logging.error("{} - could not drop DATABASE {}".format(repr(self), db_name))

            if not self.run_mysql('-e "DROP USER {};"'.format(db_user)):
                logging.error("{} - could not drop USER ".format(repr(self), db_name))

        # handle case where no wp_config found
        except ValueError as err:
            logging.warning("{} - could not clean DB: {}".format(repr(self), err))

        # clean directories before files
        logging.info("{} - removing files".format(repr(self)))
        for dir_path in settings.WP_DIRS:
            path = os.path.join(self.wp_site.path, dir_path)
            if os.path.exists(path):
                shutil.rmtree(path)

        # clean files
        for file_path in settings.WP_FILES:
            path = os.path.join(self.wp_site.path, file_path)
            if os.path.exists(path):
                os.remove(path)


class MockedWPGenerator(WPGenerator):
    """
    Class used for tests only. We don't have a LDAP server on Travis-ci so we add 'fake' webmasters without
    calling LDAP.
    """

    def validate_mockable_args(self, wp_site_url):
        pass

    def get_the_unit_id(self, unit_name):
        """
        Return a fake unit id without querying LDAP
        """
        return 42
