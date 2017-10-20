import os
import shutil
import logging
import collections
import json
import copy
import yaml


class WPPluginList:


    def __init__(self, generic_config_path, specific_config_path):
        """ Contructor

        Keyword arguments:
        generic_config_path -- Path where generic plugin configuration is stored
        specific_config_path -- Path where specific sites plugin configuration is stored
        """
        self._specific_config_path = specific_config_path

        if not os.path.exists(generic_config_path):
            logging.error("%s - Generic config path not exists: %s", repr(self), generic_config_path)

        if not os.path.exists(specific_config_path):
            logging.error("%s - Specific config path not exists: %s", repr(self), specific_config_path)


        # For specific plugins configuration
        self._generic_plugins = {}

        # Going through directory
        for plugin_name in os.listdir(generic_config_path):
            # Extracting plugin configuration
            self._generic_plugins[plugin_name] = WPPluginConfigInfos(os.path.join(generic_config_path, plugin_name))


    def __repr__(self):
        return "WPPluginList"


    def __build_plugins_for_site(self, site_name):
        """ Build specific plugin configuration for website if exists

        Keyword arguments:
        site_name -- Site for which we want the plugin list with configuration
        """



        site_specific_config_path = os.path.join(self._specific_config_path, site_name)
        # If no specific plugin configuration found for website,
        if not os.path.exists(site_specific_config_path):
            # Return default config
            return self._generic_plugins

        # Copying plugin list to have "specific one"
        specific_plugins = copy.deepcopy(self._generic_plugins)


        # Going through directory containing specific plugin configuration for site 'site_name'
        for plugin_name in os.listdir(site_specific_config_path):

            # If we already have plugin configuration,
            if plugin_name in specific_plugins:
                specific_plugins[plugin_name].merge_with_specific(os.path.join(site_specific_config_path, plugin_name))

            # We don't already have plugin configuration
            else:
                specific_plugins[plugin_name] = WPPluginConfigInfos(os.path.join(site_specific_config_path, plugin_name))

        return specific_plugins


    def plugins(self, site_name=None):
        """ Return plugin list for all sites or for specific site

        Keyword arguments:
        site_name -- Site for which we want the plugin list with configuration
        """

        # if no site, just return generic plugins
        if site_name == None:
            return self._generic_plugins

        return self.__build_plugins_for_site(site_name)



class WPPluginConfigInfos:
    """ Allow to manage configuration for a given plugin
    """


    def __init__(self, config_path):
        """ Constructor

        Keyword arguments:
        config_path -- Path to folder in which configuration files for plugin are.
        """

        if not os.path.exists(config_path):
            logging.error("%s - Plugin config path not exists: %s", repr(self), config_path)


        self.plugin_name = os.path.basename(config_path)

        plugin_zip_path = os.path.join(config_path, 'plugin.zip')
        self.zip_path = plugin_zip_path if os.path.exists(plugin_zip_path) else None

        # Let's see if we have to activate the plugin
        activate_path = os.path.join(config_path, 'activate')
        if not os.path.exists(activate_path):
            logging.error("%s - Activation file for plugin doesn't exists: %s", repr(self), activate_path)
            

        # Let's see if we have to activate the plugin or not
        self.is_active = open(activate_path, 'r').read().strip().lower() == "yes"

        option_file = os.path.join(config_path, 'options.yml')

        # If there's no options for plugin
        if not os.path.exists(option_file):
            self.options = []

        else: # Option file exists for plugin
            # Add try catch if exception ?
            self.options = yaml.load(open(option_file, 'r'))


    def __repr__(self):
        return "Plugin {} config".format(self.plugin_name)


    def merge_with_specific(self, config_path):
        """ Read 'specific' config for plugin and merge configuration with existing one.

        Keyword arguments:
        config_path -- Path to folder where specific configuration files for plugin are.
        """

        # Checking if zip file is present and override existing value if necessary
        plugin_zip_path = os.path.join(config_path, 'plugin.zip')
        if os.path.exists(plugin_zip_path):
            self.zip_path = plugin_zip_path

        # Let's see if we have to override plugin activation
        activate_path = os.path.join(config_path, 'activate')

        # If activation file exists, we have a look at its content
        if os.path.exists(activate_path):
            # Reading file content to see if we have to activate
            self.is_active = open(activate_path, 'r').read().strip().lower() == "yes"

        # Defining path to options file
        option_file = os.path.join(config_path, 'options.yml')

        # If we have an option file,
        if os.path.exists(option_file):
            # Loading specific options
            specific_options = yaml.load(open(option_file, 'r'))

            # Going through specific options
            for specific_option in specific_options:

                # Going through existing options
                for generic_option in self.options:

                    # If we found corresponding option name
                    if specific_option['option_name'] == generic_option['option_name']:
                        # We remove the existing generic option
                        self.options.remove(generic_option)

                # We add specific option at the end
                self.options.append(specific_option)
