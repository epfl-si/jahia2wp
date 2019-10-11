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
from migration2018 import GutenbergBlocks


class GutenbergFixes(GutenbergBlocks):
    """
    Provides a bunch of functions to fix some things in already migrated blocks
    """

    def __init__(self):

        super().__init__()
        # Update Regex to be able to find block names instead of shortcodes (we don't take the "epfl/" in the block name)
        self.regex_shortcode_names = r'\<!--\swp:epfl/([a-z0-9_-]+)'

        # We change fix function prefix to avoid conflicts
        self.fix_func_prefix = "_fix_block_"

    
    def _get_attribute(self, block_call, attr_name):
        """
        Return attribute value (or None if not found) for a given block call
        :param block_call: String with block call call
        :param attr_name: Attribute name for which we want the value
        :return:
        """
        matching_reg = re.compile('"{}":(".+?"|\S+?),?'.format(attr_name),
                                  re.VERBOSE | re.DOTALL)

        value = matching_reg.findall(block_call)
        # We remove surrounding " if exists.
        return value[0].strip('"') if value else None


    def _change_attribute_value(self, content, block_name, attr_name, new_value):
        """
        Change a block attribute value

        :param content: string in which doing replacement
        :param block_name: Block for which we want to change an attribute value
        :param attr_name: attribute to which we want to change value
        :param new_value: new value to set for attribute
        :return:

        FIXME
        For now, this function will only return attributes within double quotes, even 
        if not in double quotes before. So this could be a problem in some cases
        """

        # Transforms the following:
        # <!-- wp:block_name {"attr_name":"a","two":"b"} /-->  >>> <!-- wp:block_name {"attr_name":"b","two":"b"} /-->
        # <!-- wp:block_name {"attr_name":a,"two":"b"} /-->  >>> <!-- wp:block_name {"attr_name":b,"two":"b"} /-->
        
        matching_reg = re.compile('(?P<before>\<!--\swp:epfl/{}.+\{{.*?\"{}\":)(\".+?\"|\S+?)(?P<after>,|\}})'.format(block_name, attr_name),
                                  re.VERBOSE)

        return matching_reg.sub(r'\g<before>"{}"\g<after>'.format(new_value), content)


    def _get_all_block_calls(self, content, block_name, with_content=False, allow_new_lines=True):
        """
        Look for all calls for a given block in given content
        :param content: String in which to look for shortcode calls
        :param block_name: Code name to look for
        :param with_content: To tell if we have to return content as well. If given and shortcode doesn't have content,
        :param allow_new_lines: To tell if new lines are allowed in content. If they are, regex might be very greedy.
        
        :return:
        """
        regex = '\<!--\swp:epfl/{}(\s+\{{(.*?)\}})?\s+/?--\>'.format(block_name)
        if with_content:
            regex += '.*?\<!--\s/wp:epfl/{}\s+/?--\>'.format(block_name)

        if allow_new_lines:
            # re.DOTALL is to match all characters including \n
            matching_reg = re.compile("({})".format(regex), re.DOTALL)
        else:
            matching_reg = re.compile("({})".format(regex))
        
        # Because we have 3 parenthesis groups in regex, we obtain a list of tuples and we just want the first
        # element of each tuple and put it in a list.
        return [x[0] for x in matching_reg.findall(content)]


    def _fix_encoded_html(self, html, page_id):
        """
        Fix an encoded. 
        1. Decode URL
        2. Encode it into Gutenberg style
        """
        fixed_html = unquote(html)

        fixed_html = self._handle_html(fixed_html, page_id, {})

        return fixed_html


    def _fix_block_encoded_html(self, content, block_name, attr_list, page_id, add_p=False):
        """
        Fix an attribute containing encoded HTML

        :param content: content in which doing modifications
        :param block_name: Block name to look for
        :param attr_list: Block attributes list names to update
        :param page_id: Page ID
        """
        # Looking for all calls to modify them one by one
        calls = self._get_all_block_calls(content, block_name)

        for call in calls:

            new_call = call
            for attr_name in attr_list:

                data = self._get_attribute(new_call, attr_name)
                if data is not None:
                    data = self._fix_encoded_html(data, page_id)

                    if add_p:
                        data = self._add_paragraph(data, page_id, {})

                    new_call = self._change_attribute_value(new_call, block_name, attr_name, data)
            
            self._log_to_file("Before: {}".format(call))
            self._log_to_file("After: {}".format(new_call))

            self._update_report(block_name)

            content = content.replace(call, new_call)
        
        return content


    def _fix_block_google_forms(self, content, page_id):
        """
        Fix EPFL Goole Forms URL

        :param content: content to update
        :param page_id: Id of page containing content
        """
        
        return self._fix_block_encoded_html(content, 'google-forms', ['data'], page_id)
       

    def _fix_block_contact(self, content, page_id):
        """
        Fix EPFL Goole Forms URL

        :param content: content to update
        :param page_id: Id of page containing content
        """
        
        attributes = []
        for i in range(1, 4):
            attributes.append('information{}'.format(i))
        for i in range(1, 5):
            attributes.append('timetable{}'.format(i))

        return self._fix_block_encoded_html(content, 'contact', attributes, page_id, add_p=True)