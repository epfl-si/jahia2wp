import os
import logging
import copy
import yaml


""" Class declared in this file:
- WPPluginList => to manage plugin list for a website
- WPPluginConfigInfos => To store/generate configuration for a plugin.
"""

""" Defining necessary to allow usage of "!include" in YAML files.
Given path to include file can be relative to :
- Python script location
- YAML file from which "include" is done
"""


def yaml_include(loader, node):

    local_file = os.path.join(os.path.dirname(loader.stream.name), node.value)

    # if file to include exists with given valu
    if os.path.exists(node.value):
        include_file = node.value
    # if file exists with relative path to current YAML file
    elif os.path.exists(local_file):
        include_file = local_file
    else:
        logging.error("YAML include in '%s' - file to include doesn't exists: %s", loader.stream.name, node.value)

    with open(include_file) as inputfile:
        return yaml.load(inputfile)


yaml.add_constructor("!include", yaml_include)


class WPPluginList:

    def __init__(self, generic_config_path, generic_plugin_yaml, specific_config_path):
        """ Contructor

        Keyword arguments:
        generic_config_path -- Path where generic plugin configuration is stored
        generic_plugin_yaml -- name of YAML file containing generic plugin list we want to use.
        specific_config_path -- Path where specific sites plugin configuration is stored
        """
        self._specific_config_path = specific_config_path

        if not os.path.exists(generic_config_path):
            logging.error("%s - Generic config path not exists: %s", repr(self), generic_config_path)

        generic_plugin_file = os.path.join(generic_config_path, generic_plugin_yaml)
        if not os.path.exists(generic_plugin_file):
            logging.error("%s - Generic plugin list not exists: %s", repr(self), generic_plugin_file)

        if not os.path.exists(specific_config_path):
            logging.error("%s - Specific config path not exists: %s", repr(self), specific_config_path)

        # For specific plugins configuration
        self._generic_plugins = {}

        # Reading YAML file containing generic plugins
        plugin_list = yaml.load(open(generic_plugin_file, 'r'))

        # If nothing in file
        if plugin_list is None:
            logging.error("%s - YAML file seems to be empty: %s", repr(self), generic_plugin_file)

        # Going through plugins
        for plugin_infos in plugin_list['plugins']:
            # Extracting plugin configuration
            self._generic_plugins[plugin_infos['name']] = WPPluginConfigInfos(plugin_infos['name'],
                                                                              plugin_infos['config'])

    def __repr__(self):
        return "WPPluginList"

    def __build_plugins_for_site(self, site_name):
        """ Build specific plugin configuration for website if exists

        Keyword arguments:
        site_name -- Site for which we want the plugin list with configuration
        """

        site_specific_plugin_file = os.path.join(self._specific_config_path, site_name, 'plugin-list.yml')
        # If no specific plugin list found for website,
        if not os.path.exists(site_specific_plugin_file):
            # Return default config
            return self._generic_plugins

        # Copying plugin list to have "specific one"
        specific_plugins = copy.deepcopy(self._generic_plugins)

        # Reading YAML file containing specific plugins
        plugin_list = yaml.load(open(site_specific_plugin_file, 'r'))

        # If nothing in file
        if plugin_list is None:
            logging.error("%s - YAML file seems to be empty: %s", repr(self), site_specific_plugin_file)

        # Check if exists
        if 'plugins' not in plugin_list:
            logging.error("%s - YAML format error. 'plugins' key not found in file: %s",
                          repr(self), site_specific_plugin_file)

        # Going through directory containing specific plugin configuration for site 'site_name'
        for plugin_infos in plugin_list['plugins']:

            # If we already have plugin configuration,
            if plugin_infos['name'] in specific_plugins:
                specific_plugins[plugin_infos['name']].merge_with_specific(plugin_infos['config'])

            # We don't already have plugin configuration
            else:
                specific_plugins[plugin_infos['name']] = WPPluginConfigInfos(plugin_infos['name'],
                                                                             plugin_infos['config'])

        return specific_plugins

    def plugins(self, site_name=None):
        """ Return plugin list for all sites or for specific site

        Keyword arguments:
        site_name -- Site for which we want the plugin list with configuration
        """

        # if no site, just return generic plugins
        if site_name is None:
            return self._generic_plugins

        return self.__build_plugins_for_site(site_name)


class WPPluginConfigInfos:
    """ Allow to manage configuration for a given plugin. Functionalities are :
    - Init with configuration information in YAML format
    - Override existing configuration (merge_with_specific) with configuration in YAML format
    """

    def __init__(self, plugin_name, plugin_config):
        """ Constructor

        Keyword arguments:
        plugin_name -- Plugin name
        plugin_config -- Object containing configuration (coming directly from YAML file)
        """

        self.plugin_name = plugin_name

        # If we have to download from web,
        if plugin_config['src'].lower() == 'web':
            self.zip_path = None
        else:
            if not os.path.exists(plugin_config['src']):
                logging.error("%s - ZIP file not exists: %s", repr(self), plugin_config['src'])
            self.zip_path = plugin_config['src']

        # Let's see if we have to activate the plugin or not
        self.is_active = plugin_config['activate']

        # If there's no options for plugin
        if 'options' not in plugin_config:
            self.options = []

        else:  # Option file exists for plugin
            # Add try catch if exception ?
            self.options = plugin_config['options']

    def __repr__(self):
        return "Plugin {} config".format(self.plugin_name)

    def merge_with_specific(self, plugin_config):
        """ Read 'specific' config for plugin and merge configuration with existing one.

        Keyword arguments:
        plugin_config -- Object containing configuration (coming directly from YAML file)
        """

        # if "src" has been overrided
        if 'src' in plugin_config:
            # If we have to download from web,
            if plugin_config['src'].lower() == 'web':
                self.zip_path = None
            else:
                if not os.path.exists(plugin_config['src']):
                    logging.error("%s - ZIP file not exists: %s", repr(self), plugin_config['src'])
                self.zip_path = plugin_config['src']

        # If activation has been overrided
        if 'activate' in plugin_config:
            self.is_active = plugin_config['activate']

        # If there are specific options
        if 'options' in plugin_config:

            # Going through specific options
            for specific_option in plugin_config['options']:

                # Going through existing options
                for generic_option in self.options:

                    # If we found corresponding option name
                    if specific_option['option_name'] == generic_option['option_name']:
                        # We remove the existing generic option
                        self.options.remove(generic_option)

                # We add specific option at the end
                self.options.append(specific_option)
