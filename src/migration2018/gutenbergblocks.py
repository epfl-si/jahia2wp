"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018"""
from urllib.parse import quote_plus, unquote

import settings
import logging
import time
import re
import os
import json
import requests
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

    def __init__(self):

        super().__init__()
        # To store mapping "Name" to "ID" for Memento
        self.memento_mapping = {}


    def __get_memento_id(self, memento):
        """
        Returns EPFL Memento ID from name
        """

        # If we don't have information yet
        if memento not in self.memento_mapping:

            r = requests.get(url='https://memento.epfl.ch/api/v1/mementos/?search={}'.format(memento))
            
            # Nothing found
            if r.json()['count'] == 0:
                raise "Memento ID not found for '{}'".format(memento)
            # Too much found
            if r.json()['count'] > 1:
                raise "Too much Memento found for '{}'".format(memento)

            self.memento_mapping[memento] = r.json()['results'][0]['id']

        return self.memento_mapping[memento]

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

            # TODO: also handle 'themes' attribute, which is not correctly documented on Confluence... 
            logging.warning("Handle 'themes' attribute !!")

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(new_shortcode, json.dumps(attributes))

            print("Before: {}".format(call))
            print("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(old_shortcode)

        return content


    def _fix_epfl_memento_2018(self, content):
        """
        Transforms EPFL news 2018 shortcode to Gutenberg block

        """
        old_shortcode = 'epfl_memento_2018'
        new_shortcode = 'epfl/memento'

        templates_mapping = {'1': 'slider_with_the_first_highlighted_event',
                             '2': 'slider_without_the_first_highlighted_event',
                             '3': 'listing_with_the_first_highlighted_event',
                             '4': 'listing_without_the_first_highlighted_event'}

        
        
        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, old_shortcode)

        for call in calls:

            # To store new attributes
            attributes = {}

            # Generating new attributes
            attributes['memento'] = self.__get_memento_id(self._get_attribute(call, 'memento'))

            template = self._get_attribute(call, 'template')
            if template not in templates_mapping:
                raise "Undefined {} template! {}".format(old_shortcode, template)
            attributes['template'] = templates_mapping[template]

            attributes['lang'] = self._get_attribute(call, 'lang')
            attributes['category'] = self._get_attribute(call, 'category')
            attributes['period'] = self._get_attribute(call, 'period')
            attributes['keyword'] = self._get_attribute(call, 'keyword')

            # Add new attributes
            attributes['nbEvents'] = 3

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(new_shortcode, json.dumps(attributes))

            print("Before: {}".format(call))
            print("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(old_shortcode)

        return content
