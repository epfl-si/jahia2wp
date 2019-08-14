"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018"""
from urllib.parse import quote_plus, unquote

import settings
import logging
import time
import re
import os
import json
from wordpress import WPConfig, WPSite
from utils import Utils
from bs4 import BeautifulSoup
from migration2018 import Shortcodes


class GutenbergBlocks(Shortcodes): 
    """
    Provides a bunch of functions to transform 'old style' shortcodes to brand new blocks
    for amazing WordPress Gutenberg edition !

    Documentation about transformation for each shortcode can be found here :
    https://confluence.epfl.ch:8443/pages/viewpage.action?pageId=100467769
    """


    def _fix_epfl_news_2018(self, content):
        """
        Transforms EPFL news 2018 shortcode to Gutenberg block

        """
        old_shortcode = 'epfl_news_2018'
        new_shortcode = 'epfl/news'

        templates_mapping = {'1': 'listing',
                             '2': 'highlighted_with_3_news',
                             '3': 'highlighted_with_1_news',
                             '4': 'card_with_1_news',
                             '5': 'card_with_2_news',
                             '6': 'card_with_3_news'}

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, old_shortcode)

        for call in calls:

            # To store new attributes
            attributes = {}

            # Generating new attributes
            attributes['channel'] = self._get_attribute(call, 'channel')

            template = self._get_attribute(call, 'template')
            if template not in templates_mapping:
                raise "Undefined {} template! {}".format(old_shortcode, template)
            attributes['template'] = templates_mapping[template]

            if template == '1':
                attributes['nbNews'] = self._get_attribute(call, 'nb_news')

            attributes['lang'] = self._get_attribute(call, 'lang')
            
            attributes['displayLinkAllNews'] = self._get_attribute(call, 'all_news_link').lower() == 'true'
            attributes['category'] = self._get_attribute(call, 'category')

            # FIXME: also handle 'themes' attribute, which is not correctly documented on Confluence... 
            logging.warning("Handle 'themes' attribute !!")

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(new_shortcode, json.dumps(attributes))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(old_shortcode)

        return content
