import os
import shutil
import logging

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
        command = "plugin list --field=name --format=json"
        command_output = self.run_wp_cli(command)
        return False if command_output is True else self.name in command_output

    @property
    def is_activated(self):
        command = "plugin list --status=active --field=name --format=json"
        command_output = self.run_wp_cli(command)
        return False if command_output is True else self.name in command_output

    def install(self, force_reinstall=False):
        if self.config.zip_path is not None:
            param = self.config.zip_path
        else:
            param = self.name
        force_option = "--force" if force_reinstall else ""
        command = "plugin install {} {} ".format(force_option, param)
        self.run_wp_cli(command)

        # If we used a ZIP and it was generated 'on the fly', we do some cleaning
        if self.config.zip_path is not None and self.config.zipped_on_the_fly:
            os.remove(self.config.zip_path)

    def uninstall(self):
        self.run_wp_cli('plugin deactivate {}'.format(self.name))
        self.run_wp_cli('plugin uninstall {}'.format(self.name))

    def configure(self, force, **kwargs):
        """
            Config plugin via wp-cli.

        Arguments keywords
        force -- True|False if option exists, tells if it will be overrided with new value or not
        """
        # Creating object to do plugin configuration restore and lauch restore right after !
        WPPluginConfigRestore(self.wp_site).restore_config(self.config, force=force)

    def set_state(self, forced_state=None):
        """
        Change plugin state (activated, deactivated) depending on configuration

        Arguments Keyword:
        forced_state -- None|True|False Use this to override configuration and set wanted status to plugin
        """

        state = self.config.is_active if forced_state is None else forced_state

        if state:
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
        src_path = os.path.sep.join([settings.WP_FILES_PATH, self.PLUGINS_PATH, self.name])
        shutil.copyfile(src_path, self.path)

        logging.debug("%s - Plugins - %s: Copied file from %s to %s",
                      repr(self.wp_site),
                      self.name, src_path,
                      self.path)

    def uninstall(self):
        if os.path.exists(self.path):
            os.remove(self.path)

    @property
    def dir_path(self):
        dir_path = os.path.join(self.wp_site.path, self.PLUGINS_PATH)
        if not os.path.isdir(dir_path):
            os.mkdir(dir_path)
        return dir_path
