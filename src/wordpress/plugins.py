import sys
import re
import os
import logging
import copy
import yaml
import pymysql.cursors
from wordpress.models import WPSite


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


class WPPluginConfigExtractor:

    # https://github.com/PyMySQL/mysqlclient-python

    def __init__(self, openshift_env, wp_site_url):

        WP_CONFIG_DEFINE_NAMES = ['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET']

        self.wp_site_url = wp_site_url
        self.wp_site = WPSite(openshift_env, wp_site_url)

        wp_config_file = os.path.join(self.wp_site.path, "wp-config.php")

        if not os.path.exists(wp_config_file):
            logging.error("WordPress config file not exists: %s", wp_config_file)

        wp_config_file_content = open(wp_config_file, 'r').read()

        define_search_regex = re.compile("define\(\s*'(.+)'\s*,\s*'(.+)'\s*\);")
        wp_table_prefix_search_regex = re.compile("\$table_prefix\s*=\s*'(.+)'\s*;")

        self.wp_defined = {}
        # Extracting 'defined' values in WordPress config file and store in dict
        for define_name, define_value in define_search_regex.findall(wp_config_file_content):
            self.wp_defined[define_name] = define_value

        # Extracting table prefix
        result = wp_table_prefix_search_regex.findall(wp_config_file_content)
        if len(result) == 0:
            logging.error("Missing information for $wp_table_prefix in WordPress config file")
        self.wp_table_prefix = result[0]

        # Check if we have all needed 'define'
        for define_name in WP_CONFIG_DEFINE_NAMES:
            if define_name not in self.wp_defined:
                logging.error("Missing 'define' for '%s' in WordPress config file", define_name)

        """ Tables in which configuration is stored, with 'auto gen id' fields and 'unique field'
        (others than only auto-gen field). Those tables must be sorted to satisfy foreign keys """
        self.CONFIG_TABLES = {'{}postmeta'.format(self.wp_table_prefix): ['meta_id', None],
                              '{}options'.format(self.wp_table_prefix): ['option_id', 'option_name'],
                              '{}terms'.format(self.wp_table_prefix): ['term_id', None],
                              '{}termmeta'.format(self.wp_table_prefix): ['meta_id', None],
                              '{}term_taxonomy'.format(self.wp_table_prefix): ['term_taxonomy_id', None],
                              '{}term_relationships'.format(self.wp_table_prefix):
                              [None, ['object_id', 'term_taxonomy_id']]}

        """ Relation between configuration tables. There are no explicit relation between tables in DB but there are
        relation coded in WP. """
        self.TABLES_RELATIONS = {'{}termmeta'.format(self.wp_table_prefix):
                                 {'term_id': '{}terms'.format(self.wp_table_prefix)},
                                 '{}term_taxonomy'.format(self.wp_table_prefix):
                                 {'term_id': '{}terms'.format(self.wp_table_prefix)},
                                 '{}term_relationships'.format(self.wp_table_prefix):
                                 {'term_taxonomy_id': '{}term_taxonomy'.format(self.wp_table_prefix)}}

        """ Mapping between table and section in YAML file where info will be stored """
        self.TABLE_YAML_SECTION = {'{}postmeta'.format(self.wp_table_prefix): 'postmeta',
                                   '{}options'.format(self.wp_table_prefix): 'options',
                                   '{}terms'.format(self.wp_table_prefix): 'terms',
                                   '{}termmeta'.format(self.wp_table_prefix): 'termmeta',
                                   '{}term_taxonomy'.format(self.wp_table_prefix): 'term_taxonomy'}

    def _foreign_key_table(self, src_table, src_field):
        """ Return information about foreign key information if exists

        Arguments Keyword:
        src_table -- Source table for which we have to check for a foreign key
        src_field -- Field in the source table for which we have to check for a foreign key

        Return:
        - None if no foreign key
        - Target table name
        """
        if src_table in self.TABLES_RELATIONS:
            if src_field in self.TABLES_RELATIONS[src_table]:
                return self.TABLES_RELATIONS[src_table][src_field]

        return None

    def _exec_mysql_request(self, request):
        """ Allow to execute a request in database and return result. We have to open/close connection each time
        we want to execute a request because it seems to have caching of request results so 2 identical requests
        executed in the same connection will returne the same result even if changes have been done in database in
        the meantime """
        result = None
        try:
            mysql_connection = pymysql.connect(host=self.wp_defined['DB_HOST'],
                                               user=self.wp_defined['DB_USER'],
                                               password=self.wp_defined['DB_PASSWORD'],
                                               db=self.wp_defined['DB_NAME'],
                                               charset=self.wp_defined['DB_CHARSET'],
                                               cursorclass=pymysql.cursors.DictCursor)

            cur = mysql_connection.cursor()
            cur.execute(request)
            result = cur.fetchall()

        except Exception as e:
            exc_type, exc_obj, exc_tb = sys.exc_info()
            fname = os.path.split(exc_tb.tb_frame.f_code.co_filename)[1]
            print(exc_type, fname, exc_tb.tb_lineno)

        finally:
            cur.close()
            mysql_connection.close()

            return result

    def extract_config(self, output_file):
        """ Extract plugin configuration an store it in file specified by 'output_file'

        Arguments keyword:
        output_file -- Path to output file where to store plugin configuration
        """

        input("Log into WP admin console ({}/wp-admin) and navigate a bit through pages. Then, come back here \
and press ENTER: ".format(self.wp_site_url))

        """ STEP ONE - REFERENCE CONFIG """
        ref_config = {}

        # Looping through tables in which we have to recover reference configuration
        for table_name in self.CONFIG_TABLES:
            # getting reference configuration for current table
            ref_config[table_name] = self._exec_mysql_request("SELECT * FROM {}".format(table_name))

        print("")
        input("Now go on plugin page and configure it with the needed information. Then press ENTER again: ")

        """ STEP TWO - DIFFERENTIAL CONFIGURATION """
        diff_config = {}
        to_yaml = {}

        save_file = open(output_file, 'w+')

        for table_name in self.CONFIG_TABLES:

            auto_inc_field, unique_fields = self.CONFIG_TABLES[table_name]

            diff_config[table_name] = []

            rows = self._exec_mysql_request("SELECT * FROM {}".format(table_name))

            # if there is a "unique field" for current table,
            if unique_fields is not None:
                ref_fields = unique_fields if isinstance(unique_fields, list) else [unique_fields]

            elif auto_inc_field is not None:
                ref_fields = [auto_inc_field]

            # Going through differential content
            for diff_row in rows:

                row_match = False

                # Going through base rows (saved in step 1)
                for ref_row in ref_config[table_name]:

                    row_match = True
                    # Going through id/primary/unique fields to see if row match
                    for ref_field in ref_fields:

                        # If no match between same field in 'base' and 'diff' rows
                        if ref_row[ref_field] != diff_row[ref_field]:
                            row_match = False
                            break

                    # If we found the corresponding row,
                    if row_match:
                        # We can exit the loop to continue the process
                        break

                    """ If we arrive here, it means that the current 'base' row doesn't match the current 'diff'
                        row. We continue to search or... have reached the end of the loop and it means we have a
                        new row """

                # If we found the corresponding row
                if row_match:

                    """ We now have to check if 'base' and 'diff' row are equal or different (for the values not
                    used to identify the row, like id/unique/primary fields). """

                    identical_rows = True

                    # Going through fields of row to compare them
                    for key in ref_row:

                        # If key isn't used to identify the row
                        if key not in ref_fields:

                            # If the values are different,
                            if ref_row[key] != diff_row[key]:
                                identical_rows = False
                                break

                    # if rows are different
                    if not identical_rows:
                        # We store the modified row
                        diff_config[table_name].append(diff_row)

                else:  # We didn't find a corresponding row. So it means the "diff" row is a new row in the DB
                    # We store the new row
                    diff_config[table_name].append(diff_row)

            # If we have parameters to store
            if len(diff_config[table_name]) > 0:

                # We add a section for the table to save it in YAML file
                to_yaml[table_name] = []
                row_yaml = {}
                # Looping through changed/new rows in table
                for row in diff_config[table_name]:
                    # looping through fields
                    for key in row:

                        # if we have an "auto inc field" for table but it's not the current field
                        if auto_inc_field is not None and key != auto_inc_field:
                            # We save it to be present in YAML file
                            row_yaml[key] = row[key]

                # Adding the row in yaml file
                to_yaml[table_name].append(row_yaml)

        # saving configuration to YAML file
        yaml.dump(to_yaml, save_file, default_flow_style=False)

        save_file.close()

        print("\nPlugin configuration has been saved in file '{}'".format(output_file))
