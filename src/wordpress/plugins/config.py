import os
import shutil

import settings
from wordpress import WPConfig
from .manager import WPPluginConfigRestore


class WPPluginConfig(WPConfig):
    """ Relies on WPConfig to get wp_site and run wp-cli.
        Overrides is_installed to check for the theme only

    """

    WP_PLUGINS_PATH = os.path.join('wp-content', 'plugins')

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
        self.path = os.path.sep.join([self.wp_site.path, self.WP_PLUGINS_PATH, plugin_name])

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

    def uninstall(self):
        self.run_wp_cli('plugin deactivate {}'.format(self.name))
        self.run_wp_cli('plugin uninstall {}'.format(self.name))

    def configure(self):
        """
            Config plugin via wp-cli.

        """
        # Creating object to do plugin configuration restore and lauch restore right after !
        WPPluginConfigRestore(self.wp_site.openshift_env, self.wp_site.url).restore_config(self.config)

    def set_state(self):
        """
        Change plugin state (activated, deactivated)
        """
        if self.config.is_active:
            # activation through wp-cli
            self.run_wp_cli('plugin activate {}'.format(self.name))
        else:
            self.run_wp_cli('plugin deactivate {}'.format(self.name))


class WPMuPluginConfig(WPConfig):
    """ Relies on WPConfig to get wp_site and run wp-cli.
        Overrides is_installed to check for the theme only
    """

    PLUGINS_PATH = os.path.join('wp-content', 'mu-plugins')

    def __init__(self, wp_site, plugin_name):
        """
        Constructor

        Keyword arguments:
        wp_site -- Instance of class WPSite
        plugin_name -- Plugin name
        """
        super(WPMuPluginConfig, self).__init__(wp_site)
        self.name = plugin_name

        # set full path, down to file
        self.path = os.path.join(self.dir_path, plugin_name)

    def install(self):
        # copy files from jahia2wp/data/wp/wp-content/mu-plugins into domain/htdocs/folder/wp-content/mu-plugins
        src_path = os.path.sep.join([settings.WP_PATH, self.PLUGINS_PATH, self.name])
        shutil.copyfile(src_path, self.path)

    @property
    def dir_path(self):
        dir_path = os.path.join(self.wp_site.path, self.PLUGINS_PATH)
        if not os.path.isdir(dir_path):
            os.mkdir(dir_path)
        return dir_path
