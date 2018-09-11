"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018"""

import settings
import logging
import time
import re
import os
from wordpress import WPConfig, WPSite
from utils import Utils


class Shortcodes():
    """ Shortcodes helpers """

    def __init__(self):
        self.list = {}
        self.regex = r'\[([a-z_-]+)'

    def _get_site_registered_shortcodes(self, site_path):
        """
        Returns list of existing registered shortcodes for a WP site

        :param site_path: Path to site.
        :return: list of shortcodes
        """

        # Building path to PHP script
        php_script = "{}/list-registered-shortcodes.php".format(os.path.dirname(os.path.realpath(__file__)))

        registered = Utils.run_command("wp eval-file {} --path={}".format(php_script, site_path))
        return registered.split(",") if registered else []

    def locate_existing(self, path):
        """
        Locate all existing shortcodes in a given path. Go through all WordPress installs and parse pages to extract
        shortcode list.
        :param path: path where to start search
        :return:
        """

        for site_details in WPConfig.inventory(path):

            if site_details.valid == settings.WP_SITE_INSTALL_OK:

                logging.info("Checking %s...", site_details.url)

                # Getting site posts
                post_ids = Utils.run_command("wp post list --post_type=page --format=csv --fields=ID --skip-plugins "
                                             "--skip-themes --path={}".format(site_details.path))

                # Getting list of registered shortcodes to be sure to list only registered and not all strings
                # written between [ ]
                registered_shortcodes = self._get_site_registered_shortcodes(site_details.path)

                if not post_ids:
                    continue

                post_ids = post_ids.split('\n')[1:]

                # Looping through posts
                for post_id in post_ids:
                    content = Utils.run_command("wp post get {} --field=post_content --skip-plugins --skip-themes "
                                                "--path={}".format(post_id,site_details.path))

                    # Looking for all shortcodes in current post
                    for shortcode in re.findall(self.regex, content):

                        # This is not a registered shortcode
                        if shortcode not in registered_shortcodes:
                            continue

                        if shortcode not in self.list:
                            self.list[shortcode] = []

                        if site_details.path not in self.list[shortcode]:
                            self.list[shortcode].append(site_details.path)

    def __rename_shortcode(self, content, old_name, new_name):
        """
        Rename one shortcode in a given string

        :param content: string in which doing replacement
        :param old_name: shortcode name to look for
        :param new_name: new shortcode name
        :return:
        """

        # Transforms the following
        # [old_name]Content[/old_name]  --> [new_name]Content[/new_name]
        # [old_name attr="a"]           --> [new_name attr="a"]
        # [old_name]                    --> [new_name]
        #
        # <before> and <after> are used to store string before and after shortcode name and reuse it during
        # replacement with new name
        matching_reg = re.compile('(?P<before>\[(\/)?){}(?P<after> (\]|\s) )'.format(old_name), re.VERBOSE)

        return matching_reg.sub(r'\g<before>{}\g<after>'.format(new_name), content)

    def __rename_attribute(self, content, shortcode_name, attr_old_name, attr_new_name):
        """
        Rename a shortcode's attribute.

        :param content: string in which doing replacement
        :param shortcode_name: shortcode name
        :param attr_old_name: current attribute name
        :param attr_new_name: new attribute name
        :return:
        """

        # Transforms the following:
        # [my_shortcode attr_old_name="a" two="b"]  --> [my_shortcode attr_new_name="a" two="b"]
        # [my_shortcode attr_old_name=a two="b"]    --> [my_shortcode attr_new_name=a two="b"]
        # [my_shortcode attr_old_name two="b"]      --> [my_shortcode attr_new_name two="b"]
        matching_reg = re.compile('(?P<before> \[{}.+ ){}(?P<after> (=|\s|\])? )'.format(shortcode_name, attr_old_name),
                                  re.VERBOSE)

        return matching_reg.sub(r'\g<before>{}\g<after>'.format(attr_new_name), content)

    def __remove_attribute(self, content, shortcode_name, attr_name):
        """
        Remove a shortcode attribute

        :param content: string in which doing replacement
        :param shortcode_name: Shortcode name for which we have to remove attribute
        :param attr_name: Attribute name to remove
        :return:
        """

        # Transforms the following:
        # [my_shortcode attr_name="a" two="b"]  --> [my_shortcode two="b"]
        # [my_shortcode attr_name two="b"]      --> [my_shortcode two="b"]
        matching_reg = re.compile('(?P<before> \[{}.+ ){}(=(".+?"|\S+?)|\s|\])?'.format(shortcode_name, attr_name),
                                  re.VERBOSE)

        return matching_reg.sub(r'\g<before>', content)

    def __change_attribute_value(self, content, shortcode_name, attr_name, new_value):
        """
        Change a shortcode attribute value

        :param content: string in which doing replacement
        :param shortcode_name: shortcode for which we want to change an attribute value
        :param attr_name: attribute to which we want to change value
        :param new_value: new value to set for attribute
        :return:
        """

        # Transforms the following:
        # [my_shortcode attr_name="a" two="b"]  --> [my_shortcode attr_name="b" two="b"]
        # [my_shortcode attr_name=a two="b"]  --> [my_shortcode attr_name="b" two="b"]
        matching_reg = re.compile('(?P<before> \[{}.+{}= )(".+?"|\S+?)'.format(shortcode_name, attr_name),
                                  re.VERBOSE)

        return matching_reg.sub(r'\g<before>"{}"'.format(new_value), content)

    def __add_attribute(self, content, shortcode_name, attr_name, attr_value=""):
        """
        Adds an attribute to a shortcode
        :param content: string in which doing replacement
        :param shortcode_name: Shortcode name for which we want to add the attribute
        :param attr_name: Attribute name
        :param attr_value: Attribute value
        :return:
        """

        # Transforms the following
        # [my_shortcode]            --> [my_shortcode attr_name="b" ]
        # [my_shortcode two="b"]    --> [my_shortcode attr_name="b" two="b"]
        # [my_shortcode two="b"]    --> [my_shortcode attr_name two="b"]
        matching_reg = re.compile('(?P<before> \[{}.*? )(?P<after> \S+? )'.format(shortcode_name),
                                  re.VERBOSE)

        attr = attr_name
        if attr_value:
            attr += '="{}"'.format(attr_value)

        return matching_reg.sub(r'\g<before> {} \g<after>'.format(attr), content)

    def _fix_su_youtube(self, content):
        """
        Fix "su_youtube" from Shortcode ultimate plugin
        :param content:
        :return:
        """
        old_shortcode = 'su_youtube'
        new_shortcode = 'epfl_video'

        content = self.__rename_shortcode(content, old_shortcode, new_shortcode)
        return content

    def fix_site(self, openshift_env, wp_site_url):
        """
        Fix shortocdes in WP site
        :param openshift_env: openshift environment name
        :param wp_site_url: URL to website to fix.
        :return: dictionnary with report.
        """

        report = {}

        wp_site = WPSite(openshift_env, wp_site_url)
        wp_config = WPConfig(wp_site)

        if not wp_config.is_installed:
            logging.info("No WP site found at given URL (%s)", wp_site_url)
            return report

        logging.info("Fixing %s...", wp_site.folder)

        # Getting list of registered shortcodes to be sure to list only registered and not all strings
        # written between [ ]
        registered_shortcodes = self._get_site_registered_shortcodes(site_details.path)

        # Getting site posts
        post_ids = wp_config.run_wp_cli("post list --post_type=page --skip-plugins --skip-themes "
                                        "--format=csv --fields=ID")

        # Nothing to fix
        if not post_ids:
            logging.info("No page found, nothing to do!")
            return

        post_ids = post_ids.split('\n')[1:]

        # Looping through posts
        for post_id in post_ids:
            logging.info("Fixing page ID %s...", post_id)
            content = wp_config.run_wp_cli("post get {} --skip-plugins --skip-themes "
                                           "--field=post_content".format(post_id))

            # Looking for all shortcodes in current post
            for shortcode in re.findall(self.regex, content):

                # This is not a registered shortcode
                if shortcode not in registered_shortcodes:
                    logging.debug("'%s' is not registered as shortcode, skipping", shortcode)
                    continue

                fix_func_name = "_fix_{}".format(shortcode.replace("-", "_"))

                try:
                    # Trying to get function to fix current shortcode
                    fix_func = getattr(self, fix_func_name)
                except Exception as e:
                    # "Fix" function not found, skipping to next shortcode
                    continue

                logging.debug("Fixing shortcode %s...", shortcode)
                fixed_content = fix_func(content)

                if fixed_content != content:

                    # Building report
                    if shortcode not in report:
                        report[shortcode] = 0

                    report[shortcode] += 1

                    logging.debug("Shortcode fixed, updating page...")

                    for try_no in range(settings.WP_CLI_AND_API_NB_TRIES):
                        try:
                            wp_config.run_wp_cli("post update {} --skip-plugins --skip-themes - ".format(post_id),
                                                 pipe_input=fixed_content)

                        except Exception as e:
                            if try_no < settings.WP_CLI_AND_API_NB_TRIES - 1:
                                logging.error("fix_site() error. Retry %s in %s sec...",
                                              try_no + 1,
                                              settings.WP_CLI_AND_API_NB_SEC_BETWEEN_TRIES)
                                time.sleep(settings.WP_CLI_AND_API_NB_SEC_BETWEEN_TRIES)
                                pass

        return report
