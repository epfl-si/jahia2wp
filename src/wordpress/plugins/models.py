import os
import logging
import copy
import yaml

import settings

from utils import Utils


class WPPluginList:
    """ Use to manage plugin list for a WordPress site """

    def __init__(self, generic_config_path, generic_plugin_yaml, specific_config_path, site_params):
        """ Contructor

        Keyword arguments:
        generic_config_path -- Path where generic plugin configuration is stored
        generic_plugin_yaml -- name of YAML file containing generic plugin list we want to use.
        specific_config_path -- Path where specific sites plugin configuration is stored
        site_params -- Dict from CSV file acting as source of truth. This will be used to populate values in
                   YAML files containg plugins configuration using !from_csv functionality
        """
        self._specific_config_path = specific_config_path
        self._site_params = site_params

        if not os.path.exists(generic_config_path):
            logging.error("%s - Generic config path not exists: %s", repr(self), generic_config_path)

        generic_plugin_file = os.path.join(generic_config_path, generic_plugin_yaml)
        if not os.path.exists(generic_plugin_file):
            logging.error("%s - Generic plugin list not exists: %s", repr(self), generic_plugin_file)

        if not os.path.exists(specific_config_path):
            logging.error("%s - Specific config path not exists: %s", repr(self), specific_config_path)

        # For specific plugins configuration
        self._generic_plugins = {}

        # Extend possibilities of YAML reader
        yaml.add_constructor("!include", Utils.yaml_include)
        yaml.add_constructor("!from_csv", self._yaml_from_csv)
        self._yaml_from_csv_missing = set()

        # Reading YAML file containing generic plugins
        plugin_list = yaml.load(open(generic_plugin_file, 'r'))

        # If nothing in file
        if plugin_list is None:
            logging.error("%s - YAML file seems to be empty: %s", repr(self), generic_plugin_file)

        else:
            # If we have missing informations
            for missing_csv_field in self._yaml_from_csv_missing:
                logging.error("%s - YAML file CSV reference '%s' missing. Can be given with option \
'--extra-config=<YAML>'. YAML content example: '%s: <value>'", repr(self), missing_csv_field,
                              missing_csv_field)

            # If we have plugins,
            if plugin_list['plugins'] is not None:
                # Going through plugins
                for plugin_infos in plugin_list['plugins']:
                    # Extracting plugin configuration
                    self._generic_plugins[plugin_infos['name']] = WPPluginConfigInfos(plugin_infos['name'],
                                                                                      plugin_infos['config'])

    def __repr__(self):
        return "WPPluginList"

    def _yaml_from_csv(self, loader, node):
        """
        Retrieve a value (given by field name) from CSV row containing WP Site information
        Consolidate errors in set self._yaml_from_csv_missing
        """
        try:
            return Utils.yaml_from_csv(loader, node, self._site_params, raise_error=True)
        except KeyError:
            self._yaml_from_csv_missing.add(node.value)
            # We don't replace value because we can't...
            return node.value

    def __build_plugins_for_site(self, wp_site_id):
        """ Build specific plugin configuration for website if exists

        Keyword arguments:
        wp_site_id -- Site for which we want the plugin list with configuration. This must be unique !
        """

        site_specific_plugin_file = os.path.join(self._specific_config_path, wp_site_id, 'plugin-list.yml')

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

    def plugins(self, wp_site_id=None):
        """ Return plugin list for all sites or for specific site

        Keyword arguments:
        wp_site_id -- Site for which we want the plugin list with configuration. This has to be unique
        """

        # if no site, just return generic plugins
        if wp_site_id is None:
            return self._generic_plugins

        return self.__build_plugins_for_site(wp_site_id)

    def list_plugins(self, site_id, with_config=False, for_plugin=None):
        """
        List plugins (and configuration) for WP site

        Keyword arguments:
        site_id -- Site for which we want the plugin list
        with_config -- (Bool) to specify if plugin config has to be displayed
        for_plugin -- Used only if 'with_config'=True. Allow to display only configuration for one given plugin.

        return
        String with plugin list.
        """
        ret_str = "Plugin list for site '{}':\n".format(site_id)

        # Looping through plugins to display
        for plugin_name, plugin_config in self.plugins(site_id).items():

            # If we have to display information for current plugin.
            # ---
            # If we have to only display plugin list (with no configuration)
            # OR
            # IF we have to display plugin list AND configuration for ALL plugins or a GIVEN one
            if not with_config or (with_config and (for_plugin is None or for_plugin == plugin_name)):
                ret_str = "{}- {}\n".format(ret_str, plugin_name)

                ret_str = "{}  - action   : {}\n".format(ret_str, plugin_config.action)
                if plugin_config.action != settings.PLUGIN_ACTION_UNINSTALL:
                    ret_str = "{}  - activated: {}\n".format(ret_str, plugin_config.is_active)
                    if plugin_config.is_active:
                        ret_str = "{}  - src      : {}\n".format(ret_str, plugin_config.zip_path if
                                                                 plugin_config.zip_path is not None else 'web')
                # if we need to display configuration
                if with_config:
                    ret_str = "{}  - tables\n".format(ret_str)
                    for table_name in settings.WP_PLUGIN_CONFIG_TABLES:

                        ret_str = "{}    + {}\n".format(ret_str, table_name)
                        for row in plugin_config.table_rows(table_name):
                            ret_str = "{} {}\n".format(ret_str, row)

                ret_str = "{}\n".format(ret_str)

        return ret_str


class WPPluginConfigInfos:
    """ Allow to manage configuration for a given plugin. Functionalities are :
    - Init with configuration information in YAML format
    - Override existing configuration (merge_with_specific) with configuration in YAML format
    """

    def __init__(self, plugin_name, plugin_config):
        """ Constructor

        Keyword arguments:
        plugin_name -- Plugin name
        plugin_config -- Dict containing configuration (coming directly from YAML file)
        """

        self.plugin_name = plugin_name

        # Getting value if exists, otherwise set with default
        self.action = plugin_config['action'] if 'action' in plugin_config else settings.PLUGIN_ACTION_INSTALL

        # If we have to install plugin (default action), we look for several information
        if self.action == settings.PLUGIN_ACTION_INSTALL:
            # Let's see if we have to activate the plugin or not
            self.is_active = plugin_config['activate']

            # If plugin needs to be activated
            if self.is_active:
                # If we have to download from web,
                if plugin_config['src'].lower() == settings.PLUGIN_SOURCE_WP_STORE:
                    self.zip_path = None
                else:
                    # Generate full path to plugin ZIP file
                    zip_full_path = os.path.join(settings.PLUGINS_CONFIG_BASE_FOLDER, plugin_config['src'])
                    if not os.path.exists(zip_full_path):
                        logging.error("%s - ZIP file not exists: %s", repr(self), zip_full_path)
                    self.zip_path = zip_full_path

            else:  # Plugin has to be deactivated
                # So, action is set to nothing
                self.action = settings.PLUGIN_ACTION_NOTHING

        # If there's no information for DB tables (= no options) for plugin
        if 'tables' not in plugin_config:
            self.tables = {}

        else:  # table file with options exists for plugin
            # Add try catch if exception ?
            self.tables = plugin_config['tables']

    def __repr__(self):
        return "Plugin {} config".format(self.plugin_name)

    def merge_with_specific(self, specific_plugin_config):
        """ Read 'specific' config for plugin and merge configuration with existing one.

        NOTE ! Specific options only works for 'option' table.

        Keyword arguments:
        specific_plugin_config -- Dict containing specific configuration (coming directly from YAML file)
        """

        if 'action' in specific_plugin_config:
            self.action = specific_plugin_config['action']

        # if "src" has been overrided
        if 'src' in specific_plugin_config:
            # If we have to download from web,

            if specific_plugin_config['src'].lower() == settings.PLUGIN_SOURCE_WP_STORE:
                self.zip_path = None
            else:
                # Generate full path to plugin ZIP file
                zip_full_path = os.path.join(settings.PLUGINS_CONFIG_BASE_FOLDER, specific_plugin_config['src'])
                if not os.path.exists(zip_full_path):
                    logging.error("%s - ZIP file not exists: %s", repr(self), zip_full_path)
                self.zip_path = zip_full_path

        # If activation has been overrided
        if 'activate' in specific_plugin_config:
            self.is_active = specific_plugin_config['activate']

        # If there are specific options
        if 'tables' in specific_plugin_config:

            # Going through tables for which we have configuration information
            if 'options' in specific_plugin_config['tables']:

                # Going through specific options
                for specific_option in specific_plugin_config['tables']['options']:

                    # If configuration for current table is present in generic options
                    if 'options' in self.tables:
                        # Going through generic options
                        for generic_option in self.tables['options']:

                            # If we found corresponding option name
                            if specific_option['option_name'] == generic_option['option_name']:
                                # We remove the existing generic option
                                self.tables['options'].remove(generic_option)

                        # We add specific option at the end
                        self.tables['options'].append(specific_option)

                    # We dont have any information about current table in generic options
                    else:
                        # We add the option for current table
                        self.tables['options'] = [specific_option]

    def table_rows(self, table_name):
        """ Return rows (options) for specific table

        Arguments keyword:
        table_name -- Table for which we want options

        Ret:
        - dict with rows (options). Empty if no option
        """
        return {} if table_name not in self.tables else self.tables[table_name]
