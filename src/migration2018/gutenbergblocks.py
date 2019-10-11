"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018"""
from urllib.parse import unquote
from html import unescape

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
        self.report['_errors'] = []
        self.report['_nb_nested_shortcodes'] = 0
        # To store mapping "Name" to "ID" for Memento
        self.memento_mapping = {}
        # To store mapping between images ID and their URL
        self.image_mapping = {}
        # To store mapping between posts and their slug
        self.post_slug_mapping = {}
        # To store mapping between pages and their title
        self.page_title_mapping = {}
        # To store incorrect images
        self.incorrect_images = {}
        self.log_file = None



    def _get_memento_id(self, memento, page_id, extra_attr):
        """
        Returns EPFL Memento ID from name

        :param memento: memento name
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """

        # If we don't have information yet
        if memento not in self.memento_mapping:

            r = requests.get(url='https://memento.epfl.ch/api/v1/mementos/?search={}'.format(memento))


            json_result = r.json()
            # Nothing found
            if json_result['count'] == 0:
                raise ValueError("Memento ID not found for '{}'".format(memento))

            memento_id = None
            # We have to loop through results because search is done on several fields and not only on "slug", so
            # multiple results can be returned...
            for memento_infos in json_result['results']:

                if memento_infos['slug'].lower() == memento:
                    memento_id = memento_infos['id']
                    break

            if memento_id is None:
                raise ValueError("No Memento found in multiple results ({}) returned for '{}'".format(json_result['count'], memento))

            self.memento_mapping[memento] = memento_id

        return self.memento_mapping[memento]


    def _decode_url(self, url, page_id, extra_attr):
        """
        Decode given URL

        :param url: URL to decode
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """

        return unquote(url)

    
    def _unescape_url(self, url, page_id, extra_attr):
        """
        Unescape given URL

        :param url: URL to unescape
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """

        return unescape(url)


    def _epfl_schedule_datetime(self, date, page_id, extra_attr):
        """
        Generates a datetime using date information. This will be called with a date and the
        time will be given in extra_attr parameter.
        We will transform:
        2019-09-17 + 00:00:00
        To:
        2019-09-17T00:00:00

        :param date: date (start or end)
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
                            in this case, we can have 'start_time' or 'end_time' as extra_attr
        """

        time = extra_attr['start_time'] if 'start_time' in extra_attr else extra_attr['end_time']

        return "{}T{}".format(date, time)


    def _handle_html(self, content, page_id, extra_attr):
        """
        Encode HTML tags to replace <, > and " with unicode characters

        :param content: content to add into paragraph if needed
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """
        # We replace < and > with unicode
        content = content.replace('<', '\\u003c').replace('>', '\\u003e').replace('"', '\\u0022')

        return content


    def _add_paragraph(self, content, page_id, extra_attr):
        """
        Put content into a paragraph (<p> if not already into it)
        Replace \n with </p><p>

        :param content: content to add into paragraph if needed
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """

        if not content.strip().startswith("<p>"):
            # We replace new lines with </p><p>
            content = content.replace("\n", "\\u003c/p\\u003e\\u003cp\\u003e")
            
            content = self._handle_html(content, page_id, extra_attr)
            # We add <p> and </p> but encoded with unicode 
            content = '\\u003cp\\u003e{}\\u003c/p\\u003e'.format(content)

        return content


    def _add_paragraph_for_double_n(self, content, page_id, extra_attr):
        """
        Put content into a paragraph (<p> if not already into it)
        Replace \n\n with </p><p>

        :param content: content to add into paragraph if needed
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """

        if not content.strip().startswith("<p>"):

            # We replace new lines with </p><p>
            content = content.replace("\n\n", "\\u003c/p\\u003e\\u003cp\\u003e")
            
            content = self._handle_html(content, page_id, extra_attr)
            # We add <p> and </p> but encoded with unicode 
            content = '\\u003cp\\u003e{}\\u003c/p\\u003e'.format(content)

        return content


    def _get_image_url(self, image_id, page_id, extra_attr):
        """
        Returns Image URL based on its ID

        :param image_id: Id of image we want full URL
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """

        # If we don't have information yet
        if image_id not in self.image_mapping:

            image_url = self.wp_config.run_wp_cli('post get {} --field=guid'.format(image_id))

            if not image_url:

                if page_id not in self.incorrect_images:
                    self.incorrect_images[page_id] = []

                self.incorrect_images[page_id].append(image_id)

                self.image_mapping[image_id] = image_id
            else:

                self.image_mapping[image_id] = image_url

        return self.image_mapping[image_id]


    def _get_page_with_title(self, target_page_id, page_id, extra_attr):
        """
        Returns a dict with page id and slug in it.

        :param target_page_id: Id of page pointed
        :param page_id: Page ID on which is current shortcode call
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """

        if target_page_id not in self.page_title_mapping:

            self.page_title_mapping[target_page_id] = self.wp_config.run_wp_cli('post get {} --field=post_title'.format(target_page_id))

        res = {'label': self.page_title_mapping[target_page_id],
                'value': target_page_id}

        # Encoding to JSON
        return json.dumps(res, separators=(',', ':'))


    def _get_post_with_slug(self, post_id, page_id, extra_attr):
        """
        Returns a dict with post id and slug in it.

        :param post_id: Id of post
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """

        if post_id not in self.post_slug_mapping:

            self.post_slug_mapping[post_id] = self.wp_config.run_wp_cli('post get {} --field=post_name'.format(post_id))

        res = {'label': self.post_slug_mapping[post_id],
                'value': post_id}

        # Encoding to JSON
        return json.dumps(res, separators=(',', ':'))


    def _get_news_themes(self, themes, page_id, extra_attr):
        """
        Returns encoded list of dict with infos corresponding to given themes.

        :param themes: Themes, separated by a coma
        :param page_id: Page ID
        :param extra_attr: (optional) dict with extra attributes values needed by func
        """

        # Theme Id is transformed to a dict
        theme_mapping = {'1': {'value': 1,
                               'label': 'Basic Sciences'},
                         '2': {'value': 2,
                               'label': 'Health'},
                         '3': {'value': 3,
                               'label': 'Computer Science'},
                         '4': {'value': 4,
                               'label': 'Engineering'},
                         '5': {'value': 5,
                               'label': 'Environment'},
                         '6': {'value': 6,
                               'label': 'Buildings'},
                         '7': {'value': 7,
                               'label': 'Culture'},
                         '8': {'value': 8,
                               'label': 'Economy'},
                         '9': {'value': 9,
                               'label': 'Energy'}}

        res = []

        for theme_id in themes.split(','):
            theme_id = theme_id.strip()

            if theme_id not in theme_mapping:
                raise ValueError("No mapping found for theme '{}'. Page ID: {}".format(theme_id, page_id))

            res.append(theme_mapping[theme_id])

        # Encoding to JSON
        return json.dumps(res, separators=(',', ':'))


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


    def __add_attributes(self, call, attributes, attributes_desc, page_id):
        """
        Updates 'attributes' parameter (dict) with correct value depending on each attribute description
        contained in 'attributes_desc' parameter.
        If value is not found in shortcode call, it won't be added in Gutenberg block.
        If value is an integer, it will be set as an integer and not a string

        :param call: String with shortcode call
        :param attributes: dict in which we will add attribute value if exists
        :param shortcode_attributes: List with either attributes names (string) or dict with information to
                    get correct value. Informations can be:
                    'shortcode'     -> (mandatory if 'default' or 'use_content' key are not present) attribute name in shortcode call
                    'block'         -> (mandatory) attribute name in Gutenberg block

                    ** Only one of the following optional key can be present in the same time **
                    'bool'          -> (optional) to tell if value has to be transformed to a bool value (string to bool)
                    'map'           -> (optional) dict to map shortcode call attribute value to a new value.
                                        An exception is raised if no mapping is found.
                    'use_content'   -> (optional) True|False to tell to use shortcode call content for Gutenberg attribute
                                        value. (default=False)
                                        If True, ensure that 'call' parameter also contains shortcode content. See
                                    _   get_all_shortcode_calls function parameters for more information.
                                        If given, we don't have to give a value for 'shortcode' key
                    'default'       -> (optional) default value to use for Gutenberg block attribute. If given, we don't
                                        have to give a value for 'shortcode' key.

                    ** The two next keys are working together so either no one is present, either both are present **
                    'if_attr_name'  -> (optional) name of attribute to use for condition
                    'if_attr_is'    -> (optional) if 'if_attr_name' value is equal to 'if_attr_is', we will add attribute
                                        to Gutenberg block (by using options previously explained to define value).

                    ** The next key can be used with others keys because it will be taken in account only if value is NULL (None) **
                    'if_null'       -> (optional) value to use if content of shortcode value is equal to NULL (or is not present)

                    ** The next keys can be use with others key not depending on value.
                    'force_string'  -> (optional) True|False to tell if we have to force to have a string, instead of a potential integer
                    'apply_func'    -> (optional) function name to call (with shortcode call attribute value) to get
                                        value to use for Gutenberg block
                    'func_extra_attr'-> (optional) List of extra shortcode attributes that could be transmitted to function defined by 'apply_func'.
                                        This will contain a dictionnary with attribute name as key and its value as... value of course

        :param attributes_desc: Dictionnary describing shortcode attributes and how to translate them to a Gutenberg block
        :param page_id: Id of page on which we currently are
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
                        raise ValueError("Referenced attribute '{}' is not present in attribute list (maybe not encountered yet)".format(attr_desc['if_attr_name']))

                    # If referenced attribute isn't equal to conditional value, we skip current attribute
                    if attributes[attr_desc['if_attr_name']] != attr_desc['if_attr_is']:
                        continue

                # If we have to use a default value,
                if 'default' in attr_desc:
                    final_value = attr_desc['default']
                    # We can continue to next attribute


                # We have to use content as value
                if 'use_content' in attr_desc and attr_desc['use_content']:
                    final_value = self._get_content(call).strip()
                    # Changing < and >
                    final_value = final_value.replace('<', '\\u003c').replace('>', '\\u003e')


                # If code above didn't found the value,
                if final_value is None:
                    shortcode_attr = attr_desc['shortcode']

            else:
                shortcode_attr = block_attr = attr_desc

            # If code above didn't found the value,
            if final_value is None:
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
                            raise ValueError("No mapping found for attribute '{}' and value '{}'. Shortcode call: {}".format(shortcode_attr, value, call))

                        final_value = attr_desc['map'][value]

                    # Simply take the value as it is...
                    else:
                        final_value = self._handle_html(value, page_id, {})

                else: # No value was found

                    # If we have a value to set if null,
                    if 'if_null' in attr_desc:
                        final_value = attr_desc['if_null']

                    # We don't display value in block
                    else:
                        continue

            # Correct value has to be recovered using a func
            if 'apply_func' in attr_desc:
                map_func = getattr(self, attr_desc['apply_func'])

                extra_attr = {}

                # If extra shortcode attributes value needs to be given to func
                if 'func_extra_attr' in attr_desc:

                    # We loop though attributes and add them (key, value)
                    for extra_attr_name in attr_desc['func_extra_attr']:
                        extra_attr[extra_attr_name] = self._get_attribute(call, extra_attr_name)

                final_value = map_func(final_value, page_id, extra_attr)

            force_string = False if 'force_string' not in attr_desc else attr_desc['force_string']

            if not force_string and Utils.is_int(final_value):
                final_value = int(final_value)

            attributes[block_attr] = final_value


    def _fix_epfl_news_2018(self, content, page_id):
        """
        Transforms EPFL news 2018 shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
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
                                'shortcode': 'themes',
                                'block': 'themes',
                                'apply_func': '_get_news_themes',
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
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_memento_2018(self, content, page_id):
        """
        Transforms EPFL news 2018 shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
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
                                'apply_func': '_get_memento_id'
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

            try:

                # Recovering attributes from shortcode
                self.__add_attributes(call, attributes, attributes_desc, page_id)

                # We generate new shortcode from scratch
                new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

                self._log_to_file("Before: {}".format(call))
                self._log_to_file("After: {}".format(new_call))

                # Replacing in global content
                content = content.replace(call, new_call)

                self._update_report(shortcode)
            except ValueError as e:
                self._log_to_file("Page ID:{} -> Error: {}\nShortcode: {}\nNo replacement done".format(page_id, e, call))
                self.report['_errors'].append({'page_id': page_id,
                                               'error': str(e),
                                               'shortcode': call})


        return content


    def _fix_epfl_people_2018(self, content, page_id):
        """
        Transforms EPFL people 2018 shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_people_2018'
        block = 'epfl/people'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'units',
                            {
                                'shortcode': 'scipers',
                                'block': 'scipers',
                                'force_string': True
                            },
                            {
                                'shortcode': 'columns',
                                'block': 'columns',
                                'force_string': True
                            },
                            {
                                'shortcode': 'function',
                                'block': 'fonction'
                            },
                            {
                                'shortcode': 'doctoral_program',
                                'block': 'doctoralProgram'
                            }]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_infoscience_search(self, content, page_id):
        """
        Transforms EPFL Infoscience Search shortcode to Gutenberg block
        https://github.com/epfl-idevelop/jahia2wp/blob/release2018/data/wp/wp-content/plugins/epfl/shortcodes/epfl-infoscience-search/epfl-infoscience-search.php

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_infoscience_search'
        block = 'epfl/infoscience-search'

        # Looking for all calls to modify them one by one
        # we may have the two cases, to check in correct order :
        # [epfl_infoscience_search ... ] ... [/epfl_infoscience_search]
        # then
        # [epfl_infoscience_search ... /] 

        with_content_values = [True, False]

        for with_content in with_content_values:

            calls = self._get_all_shortcode_calls(content, shortcode, with_content=with_content, allow_new_lines=False)

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
                                    'shortcode': 'url',
                                    'block': 'url',
                                    'use_content': with_content,
                                    'apply_func': '_unescape_url'
                                }
                                ]


            for call in calls:

                # To store new attributes
                attributes = {}

                # Recovering attributes from shortcode
                self.__add_attributes(call, attributes, attributes_desc, page_id)

                # Handling 'groupBy' speciality
                group_by = self._get_attribute(call, 'group_by')
                group_by2 = self._get_attribute(call, 'group_by2')

                group_by_final = None
    
                if group_by == 'year':
                    if not group_by2 or group_by2 == '':
                        group_by_final = 'year'
                    elif group_by2 == 'doctype':
                        group_by_final = 'year_doctype'
                elif group_by == 'doctype':
                    if not group_by2 or group_by2 == '':
                        group_by_final = 'doctype'
                    elif group_by2 == 'year':
                        group_by_final = 'doctype_year'

                if group_by_final:
                    attributes['groupBy'] = group_by_final

                # We generate new shortcode from scratch
                new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

                self._log_to_file("Before: {}".format(call))
                self._log_to_file("After: {}".format(new_call))

                # Replacing in global content
                content = content.replace(call, new_call)

                self._update_report(shortcode)


        return content


    def _fix_epfl_card(self, content, page_id):
        """
        Transforms EPFL card shortcode to Gutenberg block
        https://github.com/epfl-idevelop/wp-theme-2018/blob/dev/wp-theme-2018/shortcodes/epfl_card/view.php
        https://github.com/epfl-idevelop/wp-gutenberg-epfl/blob/master/src/epfl-card/index.js

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_card'
        block = 'epfl/card'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [
            {
                'shortcode': 'gray_wrapper',
                'block': 'grayWrapper',
                'bool': True,
                'if_null': False
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
                'apply_func': '_get_image_url'
            })


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_faculties(self, content, page_id):
        """
        Transforms EPFL faculties (Schools) shortcode to Gutenberg block
        https://github.com/epfl-idevelop/wp-theme-2018/blob/dev/wp-theme-2018/shortcodes/schools/view.php

        :param content: content to update
        :param page_id: Id of page containing content
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
                'apply_func': '_get_image_url'
            })

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_contact(self, content, page_id):
        """
        Transforms EPFL people 2018 shortcode to Gutenberg block
        https://github.com/epfl-idevelop/wp-theme-2018/blob/dev/wp-theme-2018/shortcodes/epfl_contact/view.php

        :param content: content to update
        :param page_id: Id of page containing content
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
                                'block': 'grayWrapper',
                                'bool': True,
                                'if_null': False
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
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_definition_list(self, content, page_id):
        """
        Transforms EPFL definition list shortcode to Gutenberg block
        https://github.com/epfl-idevelop/wp-theme-2018/blob/dev/wp-theme-2018/shortcodes/definition_list/view.php

        :param content: content to update
        :param page_id: Id of page containing content
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
        for i in range(0, 10):
            for attr in multiple_attr:
                attributes_desc.append({
                    'shortcode': '{}{}'.format(attr, i),
                    'block': '{}{}'.format(attr, i+1)
                })

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_custom_teasers(self, content, page_id):
        """
        Transforms EPFL custom teaser shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
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
                'apply_func': '_get_image_url'
            })

            attributes_desc.append({
                'shortcode': 'buttonlabel{}'.format(i),
                'block': 'buttonLabel{}'.format(i)
            })

            attributes_desc.append({
                'shortcode': 'url{}'.format(i),
                'block': 'url{}'.format(i),
                'apply_func': '_unescape_url'
            })
            

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_links_group(self, content, page_id):
        """
        Transforms EPFL links group shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
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
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content
    
    def _fix_gallery(self, content, page_id):
        """
        Transforms gallery shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """

        shortcode = 'gallery'
        block = 'gallery'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = []

        attributes_desc.append({
            'shortcode': 'ids',
            'block': 'ids',
        })

        attributes_desc.append({
            'block': 'imageCrop',
            'default': False
        })

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # ids become an array, no more a string
            if 'ids' in attributes:
                attributes['ids'] = str(attributes['ids']).split(",")
                # set value to int
                attributes['ids'] = [int(i) for i in attributes['ids']]

                # dynamic additional attributes
                attributes['columns'] = len(attributes['ids'])
            else:
                attributes['columns'] = 0

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} -->'.format(block, json.dumps(attributes))
            new_call += '<ul class="wp-block-gallery columns-{} is-cropped">'.format(attributes['columns'])

            # add html code between, for every image
            for image_id in attributes['ids']:
                # do we want figcaption ?
                image_src = self._get_image_url(image_id, page_id, {})
                new_call += '''<li class="blocks-gallery-item"><figure><img src="{1}" alt="" data-id="{0}" data-link="" class="wp-image-{0}"/></figure></li>'''.format(image_id, image_src)

            new_call += '</ul>'
            new_call += '<!-- /wp:gallery -->'

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content

    def _fix_epfl_hero(self, content, page_id):
        """
        Transforms EPFL Hero shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_hero'
        block = 'epfl/hero'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'title',
                            'text',
                            {
                                'shortcode': 'image',
                                'block': 'imageId'
                            },
                            {
                                'shortcode': 'image',
                                'block': 'imageUrl',
                                'apply_func': '_get_image_url'
                            }]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_post_highlight(self, content, page_id):
        """
        Transforms EPFL post highlight shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_post_highlight'
        block = 'epfl/post-highlight'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'layout',
                            {
                                'shortcode': 'post',
                                'block': 'post',
                                'apply_func': '_get_post_with_slug',
                            }]

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_post_teaser(self, content, page_id):
        """
        Transforms EPFL post teaser shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_post_teaser'
        block = 'epfl/post-teaser'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [{
                                'shortcode': 'gray',
                                'block': 'grayBackground',
                                'bool': True
                            },
                            {
                                'shortcode': 'post',
                                'block': 'post1',
                                'apply_func': '_get_post_with_slug'
                            }]

        # For this one, we have to increment by 1 the index used at the end.
        for i in range(0, 3):
            attributes_desc.append({
                'shortcode': 'post{}'.format(i),
                'block': 'post{}'.format(i+1),
                'apply_func': '_get_post_with_slug'
            })

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_page_teaser(self, content, page_id):
        """
        Transforms EPFL page teaser shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_page_teaser'
        block = 'epfl/page-teaser'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [{
                                'shortcode': 'gray',
                                'block': 'grayBackground',
                                'bool': True
                            },
                            {
                                'shortcode': 'page',
                                'block': 'page1',
                                'apply_func': '_get_page_with_title'
                            }]

        # For this one, we have to increment by 1 the index used at the end.
        for i in range(0, 3):
            attributes_desc.append({
                'shortcode': 'page{}'.format(i),
                'block': 'page{}'.format(i+1),
                'apply_func': '_get_page_with_title'
            })

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_page_highlight(self, content, page_id):
        """
        Transforms EPFL page highlight shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_page_highlight'
        block = 'epfl/page-highlight'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'layout',
                            {
                                'shortcode': 'page',
                                'block': 'page',
                                'apply_func': '_get_page_with_title',
                            }]

        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_cover(self, content, page_id):
        """
        Transforms EPFL Cover shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_cover'
        block = 'epfl/cover'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'description',
                            {
                                'shortcode': 'image',
                                'block': 'imageId'
                            },
                            {
                                'shortcode': 'image',
                                'block': 'imageUrl',
                                'apply_func': '_get_image_url'
                            }]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_custom_highlight(self, content, page_id):
        """
        Transforms EPFL Custom Highlight shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_custom_highlight'
        block = 'epfl/custom-highlight'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'title',
                            'description',
                            'link',
                            'layout',
                            {
                                'shortcode': 'buttonlabel',
                                'block': 'buttonLabel'
                            },
                            {
                                'shortcode': 'image',
                                'block': 'imageId'
                            },
                            {
                                'shortcode': 'image',
                                'block': 'imageUrl',
                                'apply_func': '_get_image_url'
                            }]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_map(self, content, page_id):
        """
        Transforms EPFL Map shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_map'
        block = 'epfl/map'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'query']


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_google_forms(self, content, page_id):
        """
        Transforms EPFL Map shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_google_forms'
        block = 'epfl/google-forms'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ {
                                'shortcode': 'data',
                                'block': 'data',
                                'apply_func': '_decode_url'
                            },]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_introduction(self, content, page_id):
        """
        Transforms EPFL Map shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_introduction'
        block = 'epfl/introduction'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'title',
                            {
                                'shortcode': 'content',
                                'block': 'content',
                                'apply_func': '_add_paragraph_for_double_n'
                            },
                            {
                                'shortcode': 'gray',
                                'block': 'grayBackground',
                                'bool': True
                            }]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_quote(self, content, page_id):
        """
        Transforms EPFL Map shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_quote'
        block = 'epfl/quote'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'quote',
                            {
                                'shortcode': 'cite',
                                'block': 'author'
                            },
                            {
                                'shortcode': 'footer',
                                'block': 'position'
                            },
                            {
                                'shortcode': 'image',
                                'block': 'imageId'
                            },
                            {
                                'shortcode': 'image',
                                'block': 'imageUrl',
                                'apply_func': '_get_image_url'
                            }]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_social_feed(self, content, page_id):
        """
        Transforms EPFL Social Feed shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_social_feed'
        block = 'epfl/social-feed'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'height',
                            'width',
                            {
                                'shortcode': 'twitter_url',
                                'block': 'twitterUrl'
                            },
                            {
                                'shortcode': 'twitter_limit',
                                'block': 'twitterLimit'
                            },
                            {
                                'shortcode': 'instagram_url',
                                'block': 'instagramUrl'
                            },
                            {
                                'shortcode': 'facebook_url',
                                'block': 'facebookUrl'
                            }]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_tableau(self, content, page_id):
        """
        Transforms EPFL Tableau shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_tableau'
        block = 'epfl/tableau'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'height',
                            'width',
                            {
                                'shortcode': 'embed_code',
                                'block': 'embedCode',
                                'if_null': '',
                                'apply_func': '_decode_url'

                            },
                            {
                                'shortcode': 'url',
                                'block': 'tableauName',
                                'if_null': ''
                            }]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_toggle(self, content, page_id):
        """
        Transforms EPFL Toggle shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_toggle'
        block = 'epfl/toggle'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode, with_content=True)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ 'title',
                            'state',
                            {
                                'block': 'content',
                                'use_content': True,
                                'apply_func': '_add_paragraph'
                            }]


        for call in calls:

            # if nested shortcode
            if re.match(r'.+\[([a-z0-9_-]+)', call, re.DOTALL) is not None:
                self._log_to_file("Page {}, Nested block detected in {} shortcode : {}".format(page_id, shortcode, call))
                self.report['_nb_nested_shortcodes'] += 1

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_scheduler(self, content, page_id):
        """
        Transforms EPFL Toggle shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_scheduler'
        block = 'epfl/scheduler'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode, with_content=True)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = [ {
                                'shortcode': 'start_date',
                                'block': 'startDateTime',
                                'apply_func': '_epfl_schedule_datetime',
                                'func_extra_attr': ['start_time']
                            },
                            {
                                'shortcode': 'end_date',
                                'block': 'endDateTime',
                                'apply_func': '_epfl_schedule_datetime',
                                'func_extra_attr': ['end_time']
                            },
                            {
                                'block': 'content',
                                'use_content': True,
                                'apply_func': '_add_paragraph'
                            }]


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

            # We generate new shortcode from scratch
            new_call = '<!-- wp:{} {} /-->'.format(block, json.dumps(attributes))

            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            # Replacing in global content
            content = content.replace(call, new_call)

            self._update_report(shortcode)

        return content


    def _fix_epfl_video(self, content, page_id):
        """
        Transforms EPFL Video shortcode to Gutenberg block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        shortcode = 'epfl_video'
        block = 'epfl/video'

        # Looking for all calls to modify them one by one
        calls = self._get_all_shortcode_calls(content, shortcode)

        # Attribute description to recover correct value from each shortcode calls
        attributes_desc = ['url']


        for call in calls:

            # To store new attributes
            attributes = {}

            # Recovering attributes from shortcode
            self.__add_attributes(call, attributes, attributes_desc, page_id)

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

        self._log_to_file(exec_infos)

        logging.info("Log file can be found here: %s", log_filename)

        report = super().fix_site(openshift_env,
                                  wp_site_url,
                                  shortcode_name=shortcode_name,
                                  clean_textbox_div=False,
                                  simulation=simulation)

        self._log_to_file("Pages incorrect images: \n{}\n".format((json.dumps(self.incorrect_images))))

        self._log_to_file("Report: \n{}\n".format((json.dumps(report))))

        self.log_file.close()

        return report
