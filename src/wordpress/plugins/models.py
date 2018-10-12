from collections import OrderedDict

import os
import zipfile
import logging
import copy
import yaml
from utils import Utils
import settings

from wordpress import WPException


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
        self._generic_plugins = OrderedDict()

        # Extend possibilities of YAML reader
        yaml.add_constructor("!include", self._yaml_include)
        yaml.add_constructor("!from_csv", self._yaml_from_csv)
        self._yaml_from_csv_missing = []

        # Reading YAML file containing generic plugins
        plugin_list = yaml.load(open(generic_plugin_file, 'r'))

        # If nothing in file
        if plugin_list is None:
            logging.error("%s - YAML file seems to be empty: %s", repr(self), generic_plugin_file)

        else:
            # If we have missing informations
            if self._yaml_from_csv_missing:

                for missing_csv_field in self._yaml_from_csv_missing:
                    logging.error('%s - YAML file CSV reference \'%s\' missing. Can be given with option '
                                  '--extra-config=<YAML>\'. YAML content example: \'%s: <value>\'',
                                  repr(self), missing_csv_field, missing_csv_field)
                raise Exception('Please provide YAML file with needed configuration (list above) to fill missing '
                                'information for plugin configuration')

            # If we have plugins,
            if plugin_list['plugins'] is not None:
                # Going through plugins
                for plugin_infos in plugin_list['plugins']:
                    # Extracting plugin configuration
                    # plugin_infos['config'] contains content of plugin YAML configuration file
                    self._generic_plugins[plugin_infos['name']] = WPPluginConfigInfos(plugin_infos['name'],
                                                                                      plugin_infos['config'])

    def __repr__(self):
        return "WPPluginList"

    def _yaml_include(self, loader, node):
        """ Defining necessary to allow usage of "!include" in YAML files.
        Given path to include file can be relative to :
        - Python script location
        - YAML file from which "include" is done

        This can be use to include a value for a key. This value can be just a string or a complex (hiearchical)
        YAML file.
        Ex:
        my_key: !include file/with/value.yml
        """
        local_file = os.path.join(os.path.dirname(loader.stream.name), node.value)

        # if file to include exists with given valu
        if os.path.exists(node.value):
            include_file = node.value
        # if file exists with relative path to current YAML file
        elif os.path.exists(local_file):
            include_file = local_file
        else:
            error_message = "YAML include in '{}' - file to include doesn't exists: {}".format(
                                loader.stream.name, node.value)
            logging.error(error_message)
            raise WPException(error_message)

        with open(include_file) as inputfile:
            return yaml.load(inputfile)

    def _yaml_from_csv(self, loader, node):
        """
        Defining necessary to retrieve a value (given by field name) from CSV row containing WP Site information

        Ex (in YAML file):
        my_key: !from_csv field_name
        """
        # If value not exists, store the error
        if self._site_params.get(node.value, None) is None:
            try:
                self._yaml_from_csv_missing.index(node.value)

            except ValueError:  # If exception, element not in list, we add it
                self._yaml_from_csv_missing.append(node.value)

            # Returning 'None' so dict key won't be present
            return None
        else:  # No error, we return the value
            return self._site_params[node.value]

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
        plugin_config -- Dict containing configuration (coming directly from plugin YAML file)
        """

        self.plugin_name = plugin_name
        self.zipped_on_the_fly = False

        # Getting value if exists, otherwise set with default
        self.action = plugin_config['action'] if 'action' in plugin_config else settings.PLUGIN_ACTION_INSTALL

        # If we have a condition to install plugin and condition is successful
        if 'install_if' in plugin_config and \
                plugin_config['install_if']['csv_value'] == plugin_config['install_if']['equals']:
            # We ensure that plugin is not installed
            self.action = settings.PLUGIN_ACTION_UNINSTALL

        # If we have to install plugin (default action), we look for several information
        if self.action == settings.PLUGIN_ACTION_INSTALL:
            # Let's see if we have to activate the plugin or not
            self.is_active = plugin_config['activate']

            # If plugin needs to be activated
            if self.is_active:
                # If plugin is coming from WP store
                if plugin_config['src'].lower() == settings.PLUGIN_SOURCE_WP_STORE:
                    self.zip_path = None

                # If plugin is an URL pointing to a ZIP file
                elif plugin_config['src'].startswith('http') and plugin_config['src'].endswith('.zip'):
                    self.handle_plugin_remote_zip(plugin_config['src'])
                else:  # It may be a path to a local folder to use to install plugin
                    # Generate full path
                    full_path = os.path.join(settings.PLUGINS_CONFIG_BASE_FOLDER, plugin_config['src'])
                    # Do some checks and create ZIP file with plugin files if necessary
                    self.handle_plugin_local_src(full_path)

            else:  # Plugin has to be deactivated
                # So, action is set to nothing
                self.action = settings.PLUGIN_ACTION_NOTHING

        # If there's no information for DB tables (= no options) for plugin
        if 'tables' not in plugin_config:
            self.tables = {}

        else:  # table file with options exists for plugin
            # Add try catch if exception ?
            self.tables = plugin_config['tables']

        # defining if we have to use a dedicated configuration class for plugin
        self.config_class = plugin_config.get('config_class', settings.WP_DEFAULT_PLUGIN_CONFIG)

        # Getting custom config if exists (dict)
        self.config_custom = plugin_config.get('config_custom', {})

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
                # Generate full path
                full_path = os.path.join(settings.PLUGINS_CONFIG_BASE_FOLDER, specific_plugin_config['src'])
                # Do some checks and create ZIP file with plugin files if necessary
                self.handle_plugin_local_src(full_path)

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

        # If specific class to use to configure plugin,
        if 'config_class' in specific_plugin_config:
            self.config_class = specific_plugin_config['config_class']

        # If specific custom configuration,
        if 'config_custom' in specific_plugin_config:
            self.config_custom = specific_plugin_config['config_custom']

    def table_rows(self, table_name):
        """ Return rows (options) for specific table

        Arguments keyword:
        table_name -- Table for which we want options

        Ret:
        - dict with rows (options). Empty if no option
        """
        return {} if table_name not in self.tables else self.tables[table_name]

    def create_plugin_zip(self, path_to_plugin):
        """
        Create an archive from a plugin directory and save it into settings.PLUGIN_ZIP_PATH
        :param path_to_plugin: path to plugin files, including plugin directory name.
        :return: Path to created ZIP file
        """

        # Directory creation if not exists
        if not os.path.exists(settings.PLUGIN_ZIP_PATH):
            os.makedirs(settings.PLUGIN_ZIP_PATH)

        # Get current working directory to come back here after compress operation.
        initial_working_dir = os.getcwd()

        path = path_to_plugin.rstrip('/')
        # Extracting plugin dir name (we don't use self.plugin_name in case of directory name is different)
        plugin_dir_name = os.path.basename(path)
        path_to_dir = path.replace(plugin_dir_name, '')

        # Going into plugin parent directory to have only plugin folder in ZIP file (otherwise, we have full path
        # to plugin directory...)
        os.chdir(path_to_dir)

        # Generating ZIP file name
        zip_name = "{}_{}.zip".format(self.plugin_name, Utils.generate_name(10))
        zip_full_path = os.path.join(settings.PLUGIN_ZIP_PATH, zip_name)
        plugin_zip = zipfile.ZipFile(zip_full_path, 'w', zipfile.ZIP_DEFLATED)

        for root, dirs, files in os.walk(plugin_dir_name):
            for file in files:
                plugin_zip.write(os.path.join(root, file))

        os.chdir(initial_working_dir)

        plugin_zip.close()

        return zip_full_path

    def handle_plugin_local_src(self, plugin_path):
        """
        Do some check to local src given for plugin and create a ZIP with plugin files if necessary

        :param plugin_path: Path to plugin give in YAML file
        :return:
        """

        if not os.path.exists(plugin_path):
            logging.error("%s - Given path not exists: %s", repr(self), plugin_path)
        else:
            # if path is directory, it means we have to compress it to have a ZIP file
            if os.path.isdir(plugin_path):
                logging.debug("%s - Creating ZIP file from path (%s)", repr(self), plugin_path)
                # On the fly creation of plugin ZIP file
                self.zip_path = self.create_plugin_zip(plugin_path)
                self.zipped_on_the_fly = True
            else:
                self.zip_path = plugin_path
