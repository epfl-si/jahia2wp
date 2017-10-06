import os
import shutil
import logging
import subprocess

from settings import DATA_PATH
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

    def __repr__(self):
        installed_string = '[ok]' if self.is_installed else '[ko]'
        return "config {0} for {1}".format(installed_string, repr(self.wp_site))

    def run_wp_cli(self, command):
        # TODO: discuss whether we want to bubble up the exception or not ?
        try:
            cmd = "wp {} --path='{}'".format(command, self.wp_site.path)
            logging.debug("%s - WP CLI %s", self.__class__.__name__, cmd)
            subprocess.check_output(cmd, stderr=subprocess.STDOUT, shell=True)
        except subprocess.CalledProcessError as err:
            logging.error(
                "%s - WP CLI failed %s - %s - %s",
                self.__class__.__name__,
                err, err.returncode, err.output)
            return False

        # flag out success
        return True

    def run_command(self, command):
        # TODO: discuss whether we want to bubble up the exception or not ?
        try:
            logging.debug("%s - Run command %s", self.__class__.__name__, command)
            subprocess.check_output(command, stderr=subprocess.STDOUT, shell=True)
        except subprocess.CalledProcessError as err:
            logging.error(
                "%s - Run Command failed %s - %s - %s",
                self.__class__.__name__,
                err, err.returncode, err.output)
            return False

        # flag out success
        return True

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
    def db_infos(self):
        # TODO: read from wp_config.php {db_name, mysql_username, mysql_password}
        pass

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


class WPAuthConfig(WPRawConfig):
    """ Relies on WPRawConfig to get wp_site and run wp-cli.
        Overrides is_installed to check for the theme only
    """

    PLUGINS_PATH = os.path.join('wp-content', 'plugins')

    def __init__(self, wp_site, plugin_name):
        super(WPAuthConfig, self).__init__(wp_site)
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
