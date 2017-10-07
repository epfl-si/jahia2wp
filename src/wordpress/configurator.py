import os
import sys
import shutil
import logging
import subprocess

from settings import DATA_PATH, WP_CONFIG_KEYS
from utils import Utils
from .models import WPException, WPUser


class WPRawConfig:
    """ First object to implement some business logic
        - is the site installed? properly configured ?

        It provides also the methods to actually interact with WP-CLI
        - generic run_wp_cli
        - adding WP users, either from name+email or sciperID
    """

    def __init__(self, wp_site):
        self.wp_site = wp_site
        self._config_infos = None

    def __repr__(self):
        installed_string = '[ok]' if self.is_installed else '[ko]'
        return "config {0} for {1}".format(installed_string, repr(self.wp_site))

    def run_command(self, command):
        try:
            # run command and log output
            proc = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True, shell=True)
            logging.debug("%s - %s -> %s", self.__class__.__name__, command, proc.stdout)
            # return output if got any, True otherwise
            if proc.stdout:
                return proc.stdout.decode(sys.stdout.encoding)
            return True

        except subprocess.CalledProcessError as err:
            # log error with content of stderr
            logging.error(
                "%s - Run Command failed %s - %s - %s",
                self.__class__.__name__,
                err,
                err.returncode,
                err.stderr)
            return False

    def run_wp_cli(self, command):
        cmd = "wp {} --path='{}'".format(command, self.wp_site.path)
        return self.run_command(cmd)

    @property
    def is_installed(self):
        # checkt that index.php has been created
        valid_path = os.path.join(self.wp_site.path, 'index.php')
        return os.path.exists(valid_path)

    @property
    def is_config_valid(self):
        if not self.is_installed:
            return False
        return self.run_wp_cli('core is-installed')

    @property
    def is_install_valid(self):
        if not self.is_config_valid:
            return False
        # TODO: check that the site is available, that user can login and upload media
        # tests from test_wordpress
        return True

    @property
    def wp_version(self):
        return self.run_wp_cli('core version')

    def config_infos(self, field=None):
        # validate input
        if field is not None and field not in WP_CONFIG_KEYS:
            raise ValueError("field %s should be in %s", field, WP_CONFIG_KEYS)

        # lazy initialisation
        if self._config_infos is None:

            # fetch all values
            raw_infos = self.run_wp_cli('config get --format=csv')
            if not raw_infos:
                raise ValueError("%s - wp cli - Could not get config", self.wp_site.path)

            # reformat output from wp cli
            self._config_infos = {}
            for infos in Utils.csv_string_to_dict(raw_infos):
                self._config_infos[infos['key']] = infos['value']

            logging.debug("%s - wp cli - config get -> %s", self.wp_site.path, self._config_infos)

        # filter if necessary
        if field is None:
            return self._config_infos
        else:
            return self._config_infos[field]

    @property
    def db_name(self):
        return self.config_infos(field='DB_NAME')

    @property
    def db_host(self):
        return self.config_infos(field='DB_HOST')

    @property
    def db_user(self):
        return self.config_infos(field='DB_USER')

    @property
    def db_password(self):
        return self.config_infos(field='DB_PASSWORD')

    @property
    def admin_infos(self):
        # TODO: read from DB {admin_username, admin_email}
        pass

    def add_wp_user(self, username, email):
        return self._add_user(WPUser(username, email))

    def add_ldap_user(self, sciper_id):
        try:
            return self._add_user(WPUser.from_sciper(sciper_id))
        except WPException as err:
            logging.error("Generator - %s - 'add_webmasters' failed %s", repr(self), err)
            return None

    def _add_user(self, user):
        if not user.password:
            user.set_password()
            cmd = "user create {0.username} {0.email} --user_pass=\"{0.password}\" --role=administrator".format(user)
            self.run_wp_cli(cmd)

        return user


class WPThemeConfig(WPRawConfig):
    """ Relies on WPRawConfig to get wp_site and run wp-cli.
        Overrides is_installed to check for the theme only
    """

    THEMES_PATH = os.path.join('wp-content', 'themes')

    def __init__(self, wp_site, theme_name='epfl'):
        super(WPThemeConfig, self).__init__(wp_site)
        self.name = theme_name
        self.path = os.path.sep.join([self.wp_site.path, self.THEMES_PATH, theme_name])

    def __repr__(self):
        installed_string = '[ok]' if self.is_installed else '[ko]'
        return "theme {0} at {1}".format(installed_string, self.path)

    @property
    def is_installed(self):
        # check if files are found in wp-content/themes
        return os.path.isdir(self.path)

    def install(self):
        # copy files into wp-content/themes
        src_path = os.path.sep.join([DATA_PATH, self.THEMES_PATH, self.name])
        shutil.copytree(src_path, self.path)

    def activate(self):
        # use wp-cli to activate theme
        return self.run_wp_cli('theme activate {}'.format(self.name))


class WPPluginConfig(WPRawConfig):
    """ Relies on WPRawConfig to get wp_site and run wp-cli.
        Overrides is_installed to check for the theme only
    """

    PLUGINS_PATH = os.path.join('wp-content', 'plugins')

    def __init__(self, wp_site, plugin_name):
        super(WPPluginConfig, self).__init__(wp_site)
        self.name = plugin_name
        self.path = os.path.sep.join([self.wp_site.path, self.PLUGINS_PATH, plugin_name])

    def __repr__(self):
        installed_string = '[ok]' if self.is_installed else '[ko]'
        return "plugin {0} at {1}".format(installed_string, self.path)

    @property
    def is_installed(self):
        # check if files are found in wp-content/plugins
        return os.path.isdir(self.path)

    def install(self):
        # copy files into wp-content/plugins
        src_path = os.path.sep.join([DATA_PATH, self.PLUGINS_PATH, self.name])
        shutil.copytree(src_path, self.path)

    def activate(self):
        # activation through wp-cli
        self.run_wp_cli('plugin activate {}'.format(self.name))
        # configure
        cmd_path = os.path.sep.join([DATA_PATH, self.PLUGINS_PATH, 'manage-plugin-config.php'])
        cmd = 'php {0} "{1}" 3 authorizer-deny-gaspar'
        return self.run_command(cmd.format(cmd_path, self.wp_site.path))
