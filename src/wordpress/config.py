import os
import logging
import collections
import sys

import settings

from utils import Utils
from veritas.validators import validate_yes_or_no
from veritas.casters import cast_yes_or_no
from .models import WPException, WPUser, WPSite
import time


class WPConfig:
    """ First object to implement some business logic
        - is the site installed? properly configured ?

        It provides also the methods to actually interact with WP-CLI
        - generic run_wp_cli
        - adding WP users, either from name+email or sciperID
    """

    def __init__(self, wp_site,
                 installs_locked=settings.DEFAULT_CONFIG_INSTALLS_LOCKED,
                 updates_automatic=settings.DEFAULT_CONFIG_UPDATES_AUTOMATIC,
                 from_export=False):
        """
        Class constructor

        Argument keywords:
        wp_site -- Instance of WPSite class
        installs_locked -- from source of trust, wether the admin (Wordpress Role) can install new theme/plugin or not
        updates_automatic -- from source of trust, wether automatic updates are active or not
        """
        self.wp_site = wp_site
        self._config_infos = None
        self._user_infos = None

        # validate input
        if installs_locked is not None:
            validate_yes_or_no(installs_locked)
        if updates_automatic is not None:
            validate_yes_or_no(updates_automatic)

        # set additionnal options
        self.installs_locked = cast_yes_or_no(installs_locked)
        self.updates_automatic = cast_yes_or_no(updates_automatic)
        self.from_export = from_export

    def __repr__(self):
        installed_string = '[ok]' if self.is_installed else '[ko]'
        return "config {0} for {1}".format(installed_string, repr(self.wp_site))

    @classmethod
    def inventory(cls, path):
        """
        Parse path and do an inventory of existing websites.

        Argument keywords:
        path -- Path where to look for installed WordPress websites
        """
        # helper function to filter out directories which are part or WP install
        def keep_wp_sites(dir_name):
            return dir_name not in settings.WP_DIRS + settings.ENV_DIRS

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
            dir_names = sorted(dir_names)
            for dir_name in dir_names:
                logging.debug('checking %s/%s', parent_path, dir_name)
                try:
                    from_path = os.path.join(parent_path, dir_name)
                    wp_site = WPSite.from_path(from_path)
                    if wp_site is None:
                        continue
                except:
                    logging.error("Cannot extract WPSite from path '%s' - Error %s", from_path, sys.exc_info())
                    continue
                wp_config = cls(wp_site)
                if wp_config.is_config_valid:
                    yield WPResult(
                        wp_config.wp_site.path,
                        settings.WP_SITE_INSTALL_OK,
                        wp_config.wp_site.url,
                        wp_config.wp_version,
                        wp_config.db_name,
                        wp_config.db_user,
                        ",".join([wp_user.username for wp_user in wp_config.admins]),
                    )
                else:
                    yield WPResult(wp_config.wp_site.path, settings.WP_SITE_INSTALL_KO, "", "", "", "", "")

    def wp_option_exists(self, option_name):
        """
        Tells if an option exists in WordPress. This check can be done before we execute WPCLI to retrieve option
        value because if it doesn't exists, exit code will be 1 and an exception will be raised...

        :param option_name: option to check
        :return: True|False
        """
        command = "option list --search={} --format=csv --field=option_name".format(option_name)
        return self.run_wp_cli(command) is not True

    def run_wp_cli(self, command, encoding=sys.getdefaultencoding(), pipe_input=None, extra_options=None):
        """
        Execute a WP-CLI command. The command doesn't have to start with 'wp '. It will be added automatically, and
        it's the same for --path option.

        Argument keywords:
        command -- WP-CLI command to execute
        encoding -- encoding to use
        pipe_input -- Elements to give to the command using a pipe (ex: echo "elem" | wp command ...)
        extra_options -- display json in standard input. This json is used by wpcli commands
        """
        cmd = ""

        if pipe_input:
            cmd += " sh -c 'echo '\"'\"'"
            cmd += pipe_input
            cmd += "'\"'\"' |"

        cmd += "wp {} --path='{}'".format(command, self.wp_site.path)

        if extra_options:
            cmd += " "
            cmd += extra_options

        if pipe_input:
            cmd += "'"

        for try_no in range(settings.WP_CLI_AND_API_NB_TRIES):
            try:
                return Utils.run_command(cmd, encoding=encoding)
            except Exception as e:
                if try_no < settings.WP_CLI_AND_API_NB_TRIES-1:
                    logging.error("Run WPCLI error. Retry %s in %s sec...",
                                  try_no+1,
                                  settings.WP_CLI_AND_API_NB_SEC_BETWEEN_TRIES)
                    time.sleep(settings.WP_CLI_AND_API_NB_SEC_BETWEEN_TRIES)
                    pass

    @property
    def is_installed(self):
        """
        Tells if a WordPress is installed by checking if 'index.php' file is present

        Return:
        True, False
        """
        # checkt that index.php has been created
        valid_path = os.path.join(self.wp_site.path, 'index.php')
        return os.path.exists(valid_path)

    @property
    def is_config_valid(self):
        """
        Tells if WordPress configuration is valid by checking DB connection with settings present in 'wp-config.php'
        file

        Return:
        True, False
        """
        if not self.is_installed:
            return False
        return self.run_wp_cli('core is-installed')

    @property
    def is_install_valid(self):
        """
        Tells if a WordPress installation is valid

        Return:
        True, False
        """
        if not self.is_config_valid:
            return False
        # TODO: check that the site is available, that user can login and upload media
        # tests from test_wordpress
        return True

    @property
    def wp_version(self):
        """
        Returns installed WordPress version
        """
        return self.run_wp_cli('core version')

    def config_infos(self, field=None):
        """
        Extract and return WordPress configuration informations.
        Informations can be found here: https://developer.wordpress.org/cli/commands/config/get/

        Argument keywords:
        field -- (optional) configuration field for which we want the value
        """
        # validate input
        if field is not None and field not in settings.WP_CONFIG_KEYS:
            raise ValueError("Field '{}' should be in {}".format(field, settings.WP_CONFIG_KEYS))

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
        """
        Returns WordPress installation DB Name
        """
        return self.config_infos(field='DB_NAME')

    @property
    def db_host(self):
        """
        Returns WordPress installation DB host
        """
        return self.config_infos(field='DB_HOST')

    @property
    def db_user(self):
        """
        Returns WordPress installation DB user
        """
        return self.config_infos(field='DB_USER')

    @property
    def db_password(self):
        """
        Returns WordPress installation DB password for DB_USER
        """
        return self.config_infos(field='DB_PASSWORD')

    def user_infos(self, username=None):
        """
        Returns informations about all or one given user.

        Argument keywords:
        username -- (optional) Username for which we want informations

        Return:
        dictionnary with username as key and a WPUser instance as value.
        """
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
        """
        Returns a list containing of WPUser instances which have§ 'administrator' role
        """
        return [user for user in self.user_infos().values()
                if user.role == 'administrator']

    def add_wp_user(self, username, email, role='administrator'):
        """
        Add a WordPress user given by username, email and role

        Argument keywords:
        username -- Name of user to add
        email -- eMail of user to add
        role -- (optional) WordPress user's role (https://codex.wordpress.org/Roles_and_Capabilities#Summary_of_Roles)
                'administrator'
                'editor'
                'author'
                'contributor'
                'subscriber'
        """
        return self._add_user(WPUser(username, email, role=role))

    def add_ldap_user(self, sciper_id, role='administrator'):
        """
        Add a WordPress user given by its Sciper.

        Argument keywords:
        sciper_id -- New user SCIPER
        role -- (optional) WordPress user's role (https://codex.wordpress.org/Roles_and_Capabilities#Summary_of_Roles)
                'administrator'
                'editor'
                'author'
                'contributor'
                'subscriber'
        """
        try:
            return self._add_user(WPUser.from_sciper(sciper_id, role=role))
        except WPException as err:
            logging.error("%s - LDAP call failed %s", repr(self.wp_site), err)
            raise err

    def _add_user(self, user):
        """
        Add a WordPress user given by an instance of WPUser

        Argument keywords:
        user -- instance of class WPUser
        """
        if not user.password:
            user.set_password()
        cmd = "user create {0.username} {0.email} --user_pass=\"{0.password}\" --role={0.role}".format(user)
        if self.run_wp_cli(cmd) is False:
            logging.error("%s - wp user create failed. More details in the logs above", repr(self.wp_site))
        return user
