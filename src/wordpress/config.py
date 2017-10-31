import os
import shutil
import logging
import collections

from settings import DATA_PATH, ENV_DIRS, WP_DIRS, WP_CONFIG_KEYS
from utils import Utils
from .models import WPException, WPUser, WPSite
from wordpress.plugins import WPPluginConfigRestore


class WPConfig:
    """ First object to implement some business logic
        - is the site installed? properly configured ?

        It provides also the methods to actually interact with WP-CLI
        - generic run_wp_cli
        - adding WP users, either from name+email or sciperID
    """

    def __init__(self, wp_site):
        self.wp_site = wp_site
        self._config_infos = None
        self._user_infos = None

    def __repr__(self):
        installed_string = '[ok]' if self.is_installed else '[ko]'
        return "config {0} for {1}".format(installed_string, repr(self.wp_site))

    @classmethod
    def inventory(cls, wp_env, path):
        # helper function to filter out directories which are part or WP install
        def keep_wp_sites(dir_name):
            return dir_name not in WP_DIRS + ENV_DIRS

        # helper class to wrap results
        WPResult = collections.namedtuple(
            'WPResult', ['path', 'valid', 'url', 'version', 'db_name', 'db_user', 'admins'])

        # set initial path
        given_path = os.path.abspath(path)
        logging.debug('walking through %s', given_path)

        # walk through all subdirs of given path
        # topdown is true in order modify the dirnames list in-place (and exclude WP dirs)
        for (parent_path, dir_names, filenames) in os.walk(given_path, topdown=True):
            # only keep potential WP sites
            dir_names[:] = [d for d in dir_names if keep_wp_sites(d)]
            for dir_name in dir_names:
                logging.debug('checking %s/%s', parent_path, dir_name)
                wp_site = WPSite.from_path(wp_env, os.path.join(parent_path, dir_name))
                if wp_site is None:
                    continue
                wp_config = cls(wp_site)
                if wp_config.is_config_valid:
                    yield WPResult(
                        wp_config.wp_site.path,
                        "ok",
                        wp_config.wp_site.url,
                        wp_config.wp_version,
                        wp_config.db_name,
                        wp_config.db_user,
                        ",".join([wp_user.username for wp_user in wp_config.admins]),
                    )
                else:
                    yield WPResult(wp_config.wp_site.path, "KO", "", "", "", "", "")

    def run_wp_cli(self, command):
        cmd = "wp {} --path='{}'".format(command, self.wp_site.path)
        return Utils.run_command(cmd)

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
            raise ValueError("Field '{}' should be in {}".format(field, WP_CONFIG_KEYS))

        # lazy initialisation
        if self._config_infos is None:

            # fetch all values
            raw_infos = self.run_wp_cli('config get --format=csv')
            if not raw_infos:
                raise ValueError("Could not get config for {}".format(repr(self.wp_site)))

            # reformat output from wp cli
            self._config_infos = {}
            for infos in Utils.csv_string_to_dict(raw_infos):
                self._config_infos[infos['key']] = infos['value']

            logging.debug("%s - config => %s", repr(self.wp_site), self._config_infos)

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

    def user_infos(self, username=None):
        # lazy initialisation
        if self._user_infos is None:

            # fetch all values
            raw_infos = self.run_wp_cli('user list --format=csv')
            if not raw_infos:
                raise ValueError("Could not get list of users for {}".format(self.wp_site.path))

            # reformat output from wp cli
            self._user_infos = {}
            for user_infos in Utils.csv_string_to_dict(raw_infos):
                wp_user = WPUser(
                    username=user_infos['user_login'],
                    email=user_infos['user_email'],
                    display_name=user_infos['display_name'],
                    role=user_infos['roles'])

                self._user_infos[user_infos['user_login']] = wp_user

            logging.debug("%s - user list => %s", repr(self.wp_site), self._user_infos)

        # return only one user if username is given
        if username is not None:
            return self._user_infos[username]

        # return all users otherwise
        return self._user_infos

    @property
    def admins(self):
        return [user for user in self.user_infos().values()
                if user.role == 'administrator']

    def add_wp_user(self, username, email, role='administrator'):
        return self._add_user(WPUser(username, email, role=role))

    def add_ldap_user(self, sciper_id, role='administrator'):
        try:
            return self._add_user(WPUser.from_sciper(sciper_id, role=role))
        except WPException as err:
            logging.error("%s - LDAP call failed %s", repr(self.wp_site), err)
            return None

    def _add_user(self, user):
        if not user.password:
            user.set_password()
        cmd = "user create {0.username} {0.email} --user_pass=\"{0.password}\" --role={0.role}".format(user)
        self.run_wp_cli(cmd)
        return user

    def create_main_menu(self):
        # create main menu
        self.run_wp_cli('menu create Main')
        # position the main menu at the top
        self.run_wp_cli('menu location assign Main top')


class WPThemeConfig(WPConfig):
    """ Relies on WPConfig to get wp_site and run wp-cli.
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


class WPPluginConfig(WPConfig):
    """ Relies on WPConfig to get wp_site and run wp-cli.
        Overrides is_installed to check for the theme only

    """

    PLUGINS_PATH = os.path.join('wp-content', 'plugins')

    def __init__(self, wp_site, plugin_name, plugin_config):
        """
        Constructor

        Keyword arguments:
        wp_site -- Instance of class WPSite
        plugin_name -- Plugin name
        plugin_config -- Instance of class WPPluginConfigInfos
        """
        super(WPPluginConfig, self).__init__(wp_site)
        self.name = plugin_name
        self.config = plugin_config
        self.path = os.path.sep.join([self.wp_site.path, self.PLUGINS_PATH, plugin_name])

    def __repr__(self):
        installed_string = '[ok]' if self.is_installed else '[ko]'
        return "plugin {0} at {1}".format(installed_string, self.path)

    @property
    def is_installed(self):
        # check if files are found in wp-content/plugins
        return os.path.isdir(self.path)

    @property
    def is_activated(self):
        command = "plugin list --status=active --field=name --format=json"
        command_output = self.run_wp_cli(command)
        return False if command_output is True else self.name in command_output

    def install(self):
        if self.config.zip_path is not None:
            param = self.config.zip_path
        else:
            param = self.name
        command = "plugin install {0} ".format(param)
        self.run_wp_cli(command)

    def configure(self):
        """
            Config plugin via wp-cli.

        """
        # Creating object to do plugin configuration restore and lauch restore right after !
        WPPluginConfigRestore(self.wp_site).restore_config(self.config)

    def set_state(self):
        """
        Change plugin state (activated, deactivated)
        """
        if self.config.is_active:
            # activation through wp-cli
            self.run_wp_cli('plugin activate {}'.format(self.name))
        else:
            self.run_wp_cli('plugin deactivate {}'.format(self.name))
