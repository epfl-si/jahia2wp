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

        :param memento: memento name
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


    def __add_optional_attributes(self, call, attributes, shortcode_attributes):
        """
        Updates 'attributes' parameter (dict) with correct value if exists.

        :param call: String with shortcode call
        :param attributes: dict in which we will add attribute value if exists
        :param shortcode_attributes: List with either attributes names (string) or dict with shortcode attribute
                name as key an as value, the attribute name we have to use for Gutenberg Block. If string, we
                assume that Gutenberg attribute is the same as the one in the shortcode.
        """
        for attr in shortcode_attributes:

            # If it's a dictionnary, we have to recover shortcode attribute name and block attribute name
            if isinstance(attr, dict):
                shortcode_attr = list(attr)[0]
                block_attr = attr[shortcode_attr]
            else:
                shortcode_attr = block_attr = attr

            value = self._get_attribute(call, shortcode_attr)
            if value:
                attributes[block_attr] = value


    def __add_one_to_one_attributes(self, call, attributes, shortcode_attributes):
        """
        Update 'attributes' parameter (dict) for parameters we can simply recover value from shortcode call

        :param call: String with shortcode call
        :param attributes: Dict in which we will add values
        :param shortcode_attributes: List with either attributes names (string) or dict with shortcode attribute
                name as key an as value, the attribute name we have to use for Gutenberg Block. If string, we
                assume that Gutenberg attribute is the same as the one in the shortcode.
        :return:
        """

        for attr in shortcode_attributes:
            
            # If it's a dictionnary, we have to recover shortcode attribute name and block attribute name
            if isinstance(attr, dict):
                shortcode_attr = list(attr)[0]
                block_attr = attr[shortcode_attr]
            else:
                shortcode_attr = block_attr = attr

            attributes[block_attr] = self._get_attribute(call, shortcode_attr)


    def _fix_epfl_news_2018(self, content):
        """
        Transforms EPFL news 2018 shortcode to Gutenberg block

        :param content: String with page content to update
        """
        shortcode = 'epfl_news_2018'
        block = 'epfl/news'

        templates_mapping = {'1': 'listing',
                             '2': 'highlighted_with_3_news',
                             '3': 'highlighted_with_1_news',
                             '4': 'card_with_1_news',
                             '5': 'card_with_2_news',
                             '6': 'card_with_3_news'}

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # For attributes we simply need to recover
        one_to_one_recover = ['channel', 
                              'lang', 
                              'category']

        for call in calls:

            # To store new attributes
            attributes = {}

            # Generating new attributes
            
            template = self._get_attribute(call, 'template')
            if template not in templates_mapping:
                raise "Undefined {} template! {}".format(shortcode, template)
            attributes['template'] = templates_mapping[template]

            if template == '1':
                one_to_one_recover.append({'nb_news': 'nbNews'})
                
            attributes['displayLinkAllNews'] = self._get_attribute(call, 'all_news_link').lower() == 'true'

            # Recovering attributes from shortcode
            self.__add_one_to_one_attributes(call, attributes, one_to_one_recover)


            # TODO: also handle 'themes' attribute, which is not correctly documented on Confluence... 
            logging.warning("Handle 'themes' attribute !!")

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            logging.info("Before: %s", call)
            logging.info("After: %s", new_call)

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_memento_2018(self, content):
        """
        Transforms EPFL news 2018 shortcode to Gutenberg block

        :param content: String with page content to update
        """
        shortcode = 'epfl_memento_2018'
        block = 'epfl/memento'

        templates_mapping = {'1': 'slider_with_the_first_highlighted_event',
                             '2': 'slider_without_the_first_highlighted_event',
                             '3': 'listing_with_the_first_highlighted_event',
                             '4': 'listing_without_the_first_highlighted_event'}

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # For attributes we simply need to recover
        one_to_one_recover = ['lang', 
                              'category', 
                              'period', 
                              'keyword']

        for call in calls:

            # To store new attributes
            attributes = {}

            # Generating new attributes
            attributes['memento'] = self.__get_memento_id(self._get_attribute(call, 'memento'))

            template = self._get_attribute(call, 'template')
            if template not in templates_mapping:
                raise "Undefined {} template! {}".format(shortcode, template)
            attributes['template'] = templates_mapping[template]

            # Recovering attributes from shortcode
            self.__add_one_to_one_attributes(call, attributes, one_to_one_recover)

            # Add new attributes
            attributes['nbEvents'] = 3

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            logging.info("Before: %s", call)
            logging.info("After: %s", new_call)

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content


    def _fix_epfl_people_2018(self, content):
        """
        Transforms EPFL people 2018 shortcode to Gutenberg block

        :param content: String with page content to update
        """
        shortcode = 'epfl_people_2018'
        block = 'epfl/people'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # For attributes we simply need to recover
        one_to_one_recover = ['columns']
        # For optional attributes 
        optional_attributes = ['units', 
                               'scipers', 
                               'function', 
                               {'doctoral_program': 'doctoralProgram'}]

        for call in calls:

            # To store new attributes
            attributes = {}

            self.__add_optional_attributes(call, attributes, optional_attributes)
            
            # Recovering attributes from shortcode
            self.__add_one_to_one_attributes(call, attributes, one_to_one_recover)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            logging.info("Before: %s", call)
            logging.info("After: %s", new_call)

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content


    def _fix_epfl_infoscience_search(self, content):
        """
        Transforms EPFL Infoscience Search shortcode to Gutenberg block
        https://github.com/epfl-idevelop/jahia2wp/blob/release2018/data/wp/wp-content/plugins/epfl/shortcodes/epfl-infoscience-search/epfl-infoscience-search.php

        :param content: String with page content to update
        """
        shortcode = 'epfl_infoscience_search'
        block = 'epfl/infoscience-search'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode, with_content=True)

        # For attributes we simply need to recover
        one_to_one_recover = ['pattern',
                              'limit', 
                              'sort', 
                              'collection', 
                              'pattern2', 
                              'field2', 
                              {'field': 'fieldRestriction'}, 
                              'operator2', 
                              'pattern3', 
                              'field3', 
                              'operator3', 
                              'format']

        for call in calls:

            # To store new attributes
            attributes = {}

            attributes['url'] = self._get_content(call)

            # Recovering attributes from shortcode
            self.__add_one_to_one_attributes(call, attributes, one_to_one_recover)

            attributes['summary'] = self._get_attribute(call, 'summary').lower() == 'true'
            attributes['thumbnail'] = self._get_attribute(call, 'thumbnail').lower() == 'true'
            
            # Handling 'groupBy' speciality
            group_by = self._get_attribute(call, 'group_by')
            group_by2 = self._get_attribute(call, 'group_by2')

            if not group_by or group_by == '':
                group_by_final = None
            elif group_by == 'year':
                if not group_by2 or group_by2 == '':
                    group_by_final = 'year'
                elif group_by2 == 'doctype':
                    group_by_final = 'year_doctype'
            elif group_by == 'doctype':
                if not group_by2 or group_by2 == '':
                    group_by_final = 'doctype'
                elif group_by2 == 'year':
                    group_by_final = 'doctype_year'
            
            attributes['groupBy'] = group_by_final

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            logging.info("Before: %s", call)
            logging.info("After: %s", new_call)

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content