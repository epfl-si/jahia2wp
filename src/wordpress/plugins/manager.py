import os
import logging
import re
import yaml
import pymysql

from settings import WP_PLUGIN_TABLES_RELATIONS, WP_PLUGIN_CONFIG_TABLES
from wordpress import WPSite


class WPPluginConfigManager:
    """ Give necessary tools to manage (import/export) configuration parameters for a plugin which are stored
        in the database. Information to access database are recovered from WordPress config file (wp-config.php)
    """
    def __init__(self, wp_env, wp_url):
        """ Constructor

        Arguments keyword:
        wp_env - OpenShift env
        wp_url - webSite URL
        """
        # List of "defined" value to look for in "wp-config.php" file.
        WP_CONFIG_DEFINE_NAMES = ['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET']

        self.wp_site = WPSite(wp_env, wp_url)

        wp_config_file = os.path.join(self.wp_site.path, "wp-config.php")

        if not os.path.exists(wp_config_file):
            logging.error("WordPress config file not exists: %s", wp_config_file)

        wp_config_file = open(wp_config_file, 'r')
        wp_config_file_content = wp_config_file.read()
        wp_config_file.close()

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

    def _wp_table_name(self, table_short_name):
        """ Returns 'Full' WordPress table name for a table short name (which is stored in YAML file)

        Arguments keyword:
        table_short_name -- Short name of table
        """
        return '{}{}'.format(self.wp_table_prefix, table_short_name)

    def _table_short_name(self, wp_table_name):
        """ Returns table short name from a WordPress table name

        Arguments keyword
        wp_table_name -- WordPress table name
        """
        return wp_table_name.replace(self.wp_table_prefix, "")

    def _foreign_key_table(self, src_table, src_field):
        """ Return information about foreign key information if exists

        Arguments Keyword:
        src_table -- Source table for which we have to check for a foreign key
        src_field -- Field in the source table for which we have to check for a foreign key

        Return:
        - None if no foreign key
        - Target table name
        """
        if src_table in WP_PLUGIN_TABLES_RELATIONS:
            if src_field in WP_PLUGIN_TABLES_RELATIONS[src_table]:
                return WP_PLUGIN_TABLES_RELATIONS[src_table][src_field]

        return None

    def _exec_mysql_request(self, request, return_auto_insert_id=False):
        """ Allow to execute a request in database and return result. We have to open/close connection each time
        we want to execute a request because it seems to have caching of request results so 2 identical requests
        executed in the same connection will returne the same result even if changes have been done in database in
        the meantime.

        Following package is used to access MySQL https://github.com/PyMySQL/PyMySQL
        Documentation can be found here : http://pymysql.readthedocs.io/en/latest/

        Arguments keyword:
        request -- request to execute
        return_auto_insert_id -- If True, return format change to a list with : <AutoInsertId>, <dictWithResult>

        Return:
        - None if error
        - If return_auto_insert_id is False => Dict is returned
        - If return_auto_insert_id is True => return list with <AutoInsertId>, <dictWithResult>
        """
        result = None

        mysql_connection = pymysql.connect(host=self.wp_defined['DB_HOST'],
                                           user=self.wp_defined['DB_USER'],
                                           password=self.wp_defined['DB_PASSWORD'],
                                           db=self.wp_defined['DB_NAME'],
                                           charset=self.wp_defined['DB_CHARSET'],
                                           cursorclass=pymysql.cursors.DictCursor,
                                           autocommit=True)

        cur = mysql_connection.cursor()
        cur.execute(request)
        result = cur.fetchall()

        # If nothing to return,
        if return_auto_insert_id:
            # We look for last insert id.
            cur.execute("SELECT LAST_INSERT_ID() AS 'last_id'")
            # Adding information to result
            result = cur.fetchone()['last_id'], result

        cur.close()
        mysql_connection.close()

        return result

    def _addslashes(self, s):
        """ Escape quotes and double quotes in string

        Arguments keyword:
        s -- String in which to add slashes"""
        return re.sub("(\\\\|'|\")", lambda o: "\\" + o.group(1), s)


class WPPluginConfigExtractor(WPPluginConfigManager):
    """ Allows to extract plugin configuration parameters in database. This extraction needs user input during
        the procedure.
    """

    def __init__(self, wp_env, wp_url):
        """ Constructor

        Arguments keyword:
        wp_env - OpenShift env
        wp_url - webSite URL
        """
        WPPluginConfigManager.__init__(self, wp_env, wp_url)

    def extract_config(self, output_file):
        """ Extract plugin configuration an store it in file specified by 'output_file'

        Arguments keyword:
        output_file -- Path to output file where to store plugin configuration
        """

        input("Log into WP admin console ({}/wp-admin) and navigate a bit through pages. Then, come back here"
              "and press ENTER: ".format(self.wp_site.url))

        """ STEP ONE - REFERENCE CONFIG """
        ref_config = {}

        # Looping through tables in which we have to recover reference configuration
        for table_name in WP_PLUGIN_CONFIG_TABLES:
            # getting reference configuration for current table
            ref_config[table_name] = self._exec_mysql_request("SELECT * FROM {}".format(
                self._wp_table_name(table_name)))

        print("")
        input("Now go on plugin page and configure it with the needed information. Then press ENTER again: ")

        """ STEP TWO - DIFFERENTIAL CONFIGURATION """
        to_yaml = {'tables': {}}

        save_file = open(output_file, 'w+')

        for table_name in WP_PLUGIN_CONFIG_TABLES:

            diff_config = []
            auto_inc_field, unique_fields = WP_PLUGIN_CONFIG_TABLES[table_name]

            rows = self._exec_mysql_request("SELECT * FROM {}".format(self._wp_table_name(table_name)))

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
                        diff_config.append(diff_row)

                else:  # We didn't find a corresponding row. So it means the "diff" row is a new row in the DB
                    # We store the new row
                    diff_config.append(diff_row)

            # If we have parameters to store for table
            if len(diff_config) > 0:

                # We add a section for the table to save it in YAML file
                to_yaml['tables'][table_name] = diff_config

        # saving configuration to YAML file
        yaml.dump(to_yaml, save_file, default_flow_style=False)

        save_file.close()

        print("\nPlugin configuration has been saved in file '{}'".format(output_file))
        print("You now have to copy content of this file in the YAML file containing FULL plugin configuration.")


class WPPluginConfigRestore(WPPluginConfigManager):
    """ Allow to restore a given plugin configuration in WordPress database """

    def __init__(self, wp_env, wp_url):
        """ Constructor

        Arguments keyword:
        wp_env - OpenShift env
        wp_url - webSite URL
        """
        WPPluginConfigManager.__init__(self, wp_env, wp_url)

    def restore_config(self, config_infos):
        """ Restore a plugin configuration. Configuration information are stored in parameter.

        Arguments keyword:
        config_infos -- Instance of class WPPluginConfigInfos
        """
        table_id_mapping = {}

        # Looping through tables
        for table_name in WP_PLUGIN_CONFIG_TABLES:

            auto_inc_field, unique_fields = WP_PLUGIN_CONFIG_TABLES[table_name]

            # Transform to list if needed
            if not isinstance(unique_fields, list):
                unique_fields = [unique_fields]

            # Creating mapping for current table
            table_id_mapping[table_name] = {}

            # Going through rows to add in table
            for row in config_infos.table_rows(table_name):
                insert_values = {}
                update_values = []

                # Going through fields/values in the row
                for field in row:
                    value = row[field]

                    if field != auto_inc_field:

                        target_table = self._foreign_key_table(table_name, field)

                        # If we have information about foreign key,
                        if target_table is not None:

                            # If we have a mapping for the current value,
                            if value in table_id_mapping[target_table]:

                                # Getting mapped id for current value
                                current_value = table_id_mapping[target_table][value]
                            else:  # We don't have any mapping

                                """ We take the value as it is because it is probably referencing something already
                                existing in the DB (and not present in the saved configuration for the plugin) """
                                current_value = value

                        else:  # We can take the value present in the config file (with 'addslashes' to be sure)
                            current_value = self._addslashes(value)

                        # We store the value to insert
                        insert_values[field] = current_value

                        if auto_inc_field != field and field not in unique_fields:

                            update_values.append("{}='{}'".format(field, current_value))

                # Creating request to insert row or to update it if already exists
                request = "INSERT INTO {} ({}) VALUES('{}') ON DUPLICATE KEY UPDATE {}".format(
                          self._wp_table_name(table_name), ",".join(insert_values.keys()),
                          "','".join(insert_values.values()), ",".join(update_values))

                logging.debug("Request: {}".format(request))

                insert_id = self._exec_mysql_request(request, True)

                # If row wasn't inserted because already exists, (so it means we must have an 'auto-gen' field)
                if insert_id == 0 and auto_inc_field is not None:

                    # To store search conditions to find the existing row ID
                    search_conditions = []

                    # Going through unique fields
                    for unique_field_name in unique_fields:
                        search_conditions.append("{}='{}'".format(unique_field_name, row[unique_field_name]))

                    # Creating request to search existing row information
                    request = "SELECT * FROM {} WHERE {}".format(self._wp_table_name(table_name),
                                                                 " AND ".join(search_conditions))

                    logging.debug("Request: {}".format(request))

                    res = self._exec_mysql_request(request)
                    # Getting ID of existing row
                    insert_id = res[0][auto_inc_field]

                # Save ID mapping from data present in file TO row inserted (or already existing) in DB
                table_id_mapping[table_name][row[auto_inc_field]] = insert_id
