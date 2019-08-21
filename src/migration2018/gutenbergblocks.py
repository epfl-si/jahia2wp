"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018"""
from urllib.parse import quote_plus, unquote

import settings
import datetime
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

        self.log_file = None


    def _get_memento_id(self, memento):
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


    def _get_image_url(self, image_id):
        """
        Returns Image URL based on its ID

        :param image_id: Id of image we want full URL
        """   
        
        return self.wp_config.run_wp_cli('post get {} --field=guid'.format(image_id))


    def _log_to_file(self, message, display=False):
        """
        Log a message into a file

        :param message: Message to log in file
        :param display: (optional) True|False to tell if we have to display message in console or not.
        """
        now = datetime.datetime.now()
        self.log_file.write("[{}]: {}\n".format(now.strftime("%Y-%m-%d %H:%M:%S"), message).encode())

        if display:
            logging.info(message)


    def __add_attributes(self, call, attributes, attributes_desc):
        """
        Updates 'attributes' parameter (dict) with correct value depending on each attribute description
        contained in 'attributes_desc' parameter.
        If value is not found in shortcode call, it won't be added in Gutenberg block.
        If value is an integer, it will be set as an integer and not a string

        :param call: String with shortcode call
        :param attributes: dict in which we will add attribute value if exists
        :param shortcode_attributes: List with either attributes names (string) or dict with information to
                    get correct value. Informations can be:
                    'shortcode' -> (mandatory if 'default' or 'use_content' key are not present) attribute name in shortcode call
                    'block'     -> (mandatory) attribute name in Gutenberg block 

                    ** Only one of the following optional key can be present in the same time **
                    'bool'      -> (optional) to tell if value has to be transformed to a bool value (string to bool)
                    'map'       -> (optional) dict to map shortcode call attribute value to a new value.
                                    An exception is raised if no mapping is found.
                    'map_func'  -> (optional) function name to call (with shortcode call attribute value) to get
                                    value to use for Gutenberg block
                    'use_content'-> (optional) True|False to tell to use shortcode call content for Gutenberg attribute
                                    value. (default=False)
                                    If True, ensure that 'call' parameter also contains shortcode content. See 
                                    _get_all_shortcode_calls function parameters for more information.
                                    If given, we don't hvae to give a value for 'shortcode' key
                    'default'   -> (optional) default value to use for Gutenberg block attribute. If given, we don't 
                                    have to give a value for 'shortcode' key.
                    

                    ** The two next keys are working together so either no one is present, either both are present **
                    'if_attr_name'  -> (optional) name of attribute to use for condition
                    'if_attr_is'    -> (optional) if 'if_attr_name' value is equal to 'if_attr_is', we will add attribute
                                        to Gutenberg block (by using options previously explained to define value).

                    ** The next key can be used with others keys because it will be taken in account only if value is NULL (None) **
                    'if_null'   -> (optional) value to use if content of shortcode value is equal to NULL (or is not present)    
        """

        for attr_desc in attributes_desc:

            final_value = None

            # If it's a dictionnary, we have to recover shortcode attribute name and block attribute name
            if isinstance(attr_desc, dict):

                block_attr = attr_desc['block']

                # if we have condition for attribute presence
                if 'if_attr_name' in attr_desc and 'if_attr_is' in attr_desc:
                    # if attribute we have to look for is not present
                    if attr_desc['if_attr_name'] not in attributes:
                        raise "Referenced attribute '{}' is not present in attribute list (maybe not encountered yet)".format(attr_desc['if_attr_name'])
                    
                    # If referenced attribute isn't equal to conditional value, we skip current attribute
                    if attributes[attr_desc['if_attr_name']] != attr_desc['if_attr_is']:
                        continue

                # If we have to use a default value, 
                if 'default' in attr_desc:
                    final_value = attr_desc['default']
                    # We can continue to next attribute
                    
                
                # We have to use content as value
                if 'use_content' in attr_desc and attr_desc['use_content']:
                    final_value = self._get_content(call)
                    
                # If code above didn't found the value,    
                if not final_value:
                    shortcode_attr = attr_desc['shortcode']
                
            else:
                shortcode_attr = block_attr = attr_desc

            # If code above didn't found the value,
            if not final_value:
                # Recovering source value
                value = self._get_attribute(call, shortcode_attr)
                # If value is found
                if value:
                    # We need to transform string to bool
                    if 'bool' in attr_desc and attr_desc['bool']:
                        final_value = value.lower() == 'true'
                    
                    # Value has to be mapped to another using dict
                    elif 'map' in attr_desc:
                        # If there's no mapping, we raise an exception.
                        if value not in attr_desc['map']:
                            raise "No mapping found for attribute '{}' and value '{}'. Shortcode call: {}".format(shortcode_attr, value, call)
                        final_value = attr_desc['map'][value]
                    
                    # Correct value has to be recovered using a func
                    elif 'map_func' in attr_desc:
                        map_func = getattr(self, attr_desc['map_func'])
                        final_value = map_func(value)

                    # Simply take the value as it is...
                    else:
                        final_value = value

                else: # No value was found 

                    # If we have a value to set if null,
                    if 'if_null' in attr_desc:
                        final_value = attr_desc['if_null']
                    
                    # We don't display value in block
                    else:
                        continue

            if Utils.is_int(final_value):
                final_value = int(final_value)

            attributes[block_attr] = final_value


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

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'channel',
                            'lang', 
                            'category',
                            {
                                'shortcode': 'all_news_link',
                                'block': 'displayLinkAllNews',
                                'bool': True
                            },
                            {
                                'shortcode': 'template',
                                'block': 'template',
                                'map': templates_mapping
                            },
                            {
                                'shortcode': 'nb_news',
                                'block': 'nbNews',
                                'if_attr_name': 'template',
                                'if_attr_is': 'listing'
                            }]

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

            # TODO: also handle 'themes' attribute, which is not correctly documented on Confluence... 
            logging.warning("EPFL News 2018 - Handle 'themes' attribute !!")

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

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

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'lang', 
                            'category', 
                            'period', 
                            'keyword',
                            {
                                'shortcode': 'memento',
                                'block': 'memento',
                                'map_func': '_get_memento_id'
                            },
                            {
                                'shortcode': 'template',
                                'block': 'template',
                                'map': templates_mapping
                            },
                            {
                                'block': 'nbEvents',
                                'default': 3
                            }]

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

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

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'columns',
                            'units',
                            'scipers', 
                            'function',
                            {
                                'shortcode': 'doctoral_program',
                                'block': 'doctoralProgram'
                            }]
        

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

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

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'pattern',
                            'pattern2', 
                            'pattern3', 
                            'limit', 
                            'sort', 
                            'collection', 
                            'field2', 
                            'field3', 
                            'operator2', 
                            'operator3', 
                            'format',
                            {
                                'shortcode': 'field',
                                'block': 'fieldRestriction'
                            }, 
                            {
                                'shortcode': 'summary',
                                'block': 'summary',
                                'bool': True
                            },
                            {
                                'shortcode': 'thumbnail',
                                'block': 'thumbnail',
                                'bool': True
                            },
                            {
                                'block': 'url',
                                'use_content': True
                            }
                            ]

        for call in calls:

            # To store new attributes
            attributes = {}
            
            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

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

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content


    def _fix_epfl_card(self, content):
        """
        Transforms EPFL card shortcode to Gutenberg block
        https://github.com/epfl-idevelop/wp-theme-2018/blob/dev/wp-theme-2018/shortcodes/epfl_card/view.php
        https://github.com/epfl-idevelop/wp-gutenberg-epfl/blob/master/src/epfl-card/index.js

        :param content: String with page content to update
        """
        shortcode = 'epfl_card'
        block = 'epfl/card'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [
            {
                'shortcode': 'gray_wrapper',
                'block': 'grayWrapper'
            }
            ]

        multiple_attr = ['title', 
                         'link', 
                         'content']

        # We add multiple attributes
        for i in range(1, 5):
            for attr in multiple_attr:
                attributes_desc.append('{}{}'.format(attr, i))
            
            attributes_desc.append({
                'shortcode': 'image{}'.format(i),
                'block': 'imageId{}'.format(i)
            })

            attributes_desc.append({
                'shortcode': 'image{}'.format(i),
                'block': 'imageUrl{}'.format(i),
                'map_func': '_get_image_url'
            })


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content


    def _fix_epfl_faculties(self, content):
        """
        Transforms EPFL faculties (Schools) shortcode to Gutenberg block
        https://github.com/epfl-idevelop/wp-theme-2018/blob/dev/wp-theme-2018/shortcodes/schools/view.php

        :param content: String with page content to update
        """
        shortcode = 'epfl_faculties'
        block = 'epfl/caption-cards'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = []
        
        multiple_attr = ['title', 
                         'link', 
                         'subtitle']

        # We add multiple attributes
        for i in range(1, 11):
            for attr in multiple_attr:
                attributes_desc.append('{}{}'.format(attr, i))
            
            attributes_desc.append({
                'shortcode': 'image{}'.format(i),
                'block': 'imageId{}'.format(i)
            })

            attributes_desc.append({
                'shortcode': 'image{}'.format(i),
                'block': 'imageUrl{}'.format(i),
                'map_func': '_get_image_url'
            })

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content


    def _fix_epfl_contact(self, content):
        """
        Transforms EPFL people 2018 shortcode to Gutenberg block
        https://github.com/epfl-idevelop/wp-theme-2018/blob/dev/wp-theme-2018/shortcodes/epfl_contact/view.php

        :param content: String with page content to update
        """
        shortcode = 'epfl_contact'
        block = 'epfl/contact'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'introduction',
                            {
                                'shortcode': 'map_query',
                                'block': 'mapQuery'
                            },
                            {
                                'shortcode': 'gray_wrapper',
                                'block': 'grayWrapper'
                            }]
        
        # We add multiple attributes
        for i in range(1, 5):
            attributes_desc.append('timetable{}'.format(i))

        for i in range(1, 4):
            attributes_desc.append('information{}'.format(i))

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content


    def _fix_epfl_definition_list(self, content):
        """
        Transforms EPFL definition list shortcode to Gutenberg block
        https://github.com/epfl-idevelop/wp-theme-2018/blob/dev/wp-theme-2018/shortcodes/definition_list/view.php

        :param content: String with page content to update
        """
        shortcode = 'epfl_definition_list'
        block = 'epfl/definition-list'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ {
                                'shortcode': 'tabledisplay',
                                'block': 'tableDisplay',
                                'bool': True,
                                'if_null': False
                            },
                            {
                                'shortcode': 'largedisplay',
                                'block': 'largeDisplay',
                                'bool': True,
                                'if_null': False
                            }]
        

        multiple_attr = ['label', 
                         'desc']

        # We add multiple attributes
        for i in range(1, 11):
            for attr in multiple_attr:
                attributes_desc.append('{}{}'.format(attr, i))

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content


    def _fix_epfl_custom_teasers(self, content):
        """
        Transforms EPFL custom teaser shortcode to Gutenberg block
        
        :param content: String with page content to update
        """
        shortcode = 'epfl_custom_teasers'
        block = 'epfl/custom-teaser'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ {
                                'shortcode': 'titlesection',
                                'block': 'titleSection'
                            },
                            {
                                'shortcode': 'graybackground',
                                'block': 'grayBackground',
                                'bool': True,
                                'if_null': False
                            }]
        

        multiple_attr = ['title',
                         'url',
                         'excerpt']
                         

        # We add multiple attributes
        for i in range(1, 4):
            for attr in multiple_attr:
                attributes_desc.append('{}{}'.format(attr, i))
            
            attributes_desc.append({
                'shortcode': 'image{}'.format(i),
                'block': 'imageId{}'.format(i)
            })

            attributes_desc.append({
                'shortcode': 'image{}'.format(i),
                'block': 'image{}'.format(i),
                'map_func': '_get_image_url'
            })

            attributes_desc.append({
                'shortcode': 'buttonlabel{}'.format(i),
                'block': 'buttonLabel{}'.format(i)
            })

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content


    def _fix_epfl_links_group(self, content):
        """
        Transforms EPFL links group shortcode to Gutenberg block
        
        :param content: String with page content to update
        """
        shortcode = 'epfl_links_group'
        block = 'epfl/links-group'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = ['title',
                            {
                                'shortcode': 'main_url',
                                'block': 'mainUrl'
                            }]
        
        # We add multiple attributes. 
        # For those ones, we have to increment by 1 the index used at the end.
        for i in range(0, 10):
            attributes_desc.append({
                'shortcode': 'label{}'.format(i),
                'block': 'label{}'.format(i+1)
            })
            attributes_desc.append({
                'shortcode': 'url{}'.format(i),
                'block': 'url{}'.format(i+1)
            })

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)
            
            self._update_report(shortcode)

        return content


    def fix_site(self, openshift_env, wp_site_url, shortcode_name=None, simulation=False):
        """
        Fix shortocdes in WP site
        :param openshift_env: openshift environment name
        :param wp_site_url: URL to website to fix.
        :param shortcode_name: fix site for this shortcode only
        :return: dictionnary with report.
        """

        log_filename = os.path.join(settings.MIGRATION_LOG_PATH, "{}.log".format(wp_site_url.replace(":", "_").replace("/", "_")))

        self.log_file = open(log_filename, mode='ab')

        exec_infos = "!! {} !!\n".format("Simulation" if simulation else "Normal execution")

        self.log_file.write(exec_infos.encode())

        logging.info("Log file can be found here: %s", log_filename)

        report = super().fix_site(openshift_env, wp_site_url, shortcode_name)

        self.log_file.close()

        return report
