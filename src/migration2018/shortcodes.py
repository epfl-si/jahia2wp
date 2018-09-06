"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018"""

import settings
import re
from wordpress import WPConfig
from utils import Utils


class Shortcodes():
    """ Shortcodes helpers """

    def __init__(self):
        self.shortcode_list = {}

    def _locate_in_pages(self, path_to_site):
        return ""

    def locate_existing(self, path):
        """
        Locate all existing shortcodes in a given path. Go through all WordPress installs and parse pages to extract
        shortcode list.
        :param path:
        :return:
        """
        for site_details in WPConfig.inventory(path):

            if site_details.valid == settings.WP_SITE_INSTALL_OK:

                print("Checking {}...".format(site_details.url))

                # Getting site posts
                post_ids = Utils.run_command("wp post list --post_type=page --format=csv --fields=ID --path={}".format(
                    site_details.path))

                if not post_ids:
                    continue

                post_ids = post_ids.split('\n')[1:]

                # Looping through posts
                for post_id in post_ids:
                    content = Utils.run_command("wp post get {} --field=post_content --path={}".format(
                        post_id,
                        site_details.path))

                    # Looking for all shortcodes in current post
                    for shortcode in re.findall(r'\[([a-z_]+)', content):

                        if shortcode not in self.shortcode_list:
                            self.shortcode_list[shortcode] = []

                            self.shortcode_list[shortcode].append(site_details.path)
