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
        matching_reg = re.compile('"{}":\s?(".+?"|\S+?)(,|\}})'.format(attr_name),
                                  re.VERBOSE | re.DOTALL)

        value = matching_reg.findall(block_call)
        # We remove surrounding " if exists.
        return value[0][0].strip('"') if value else None


    def _change_attribute_value(self, content, block_name, attr_name, new_value, between_double_quotes=True):
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
        
        matching_reg = re.compile('(?P<before>\<!--\swp:epfl/{}.+\{{.*?\"{}\":\s?)(\".+?\"|\S+?)(?P<after>,|\}})'.format(block_name, attr_name),
                                  re.VERBOSE)

        double_quotes = '"' if between_double_quotes else ''

        return matching_reg.sub(r'\g<before>{0}{1}{0}\g<after>'.format(double_quotes, new_value), content)

    
    def _remove_block_call_content(self, block_call, block_name):
        """
        Remove content of a block call to transform it back to a "simple" block

        :param block_call: Block call
        :param block_name: Block name
        """

        # We look for first part of block
        block_start_reg = re.compile('(\<!--\swp:epfl/{}(\s+\{{(.*?)\}})?\s+--\>)'.format(block_name))

        # We take result and retransform it to "simple block"
        all = block_start_reg.findall(block_call)

        return all[0][0].replace("-->", "/-->")


    def _get_all_block_calls(self, content, block_name, with_content=False):
        """
        Look for all calls for a given block in given content
        :param content: String in which to look for shortcode calls
        :param block_name: Code name to look for
        :param with_content: To tell if we have to return content as well. If given and shortcode doesn't have content,
        
        :return:
        """
        regex = '\<!--\swp:epfl/{}(\s+\{{(.*?)\}})?\s+{}--\>'.format(block_name, ("" if with_content else "/"))
        if with_content:
            regex += '.*?\<!--\s/wp:epfl/{}\s+--\>'.format(block_name)
            
            # We have to look through multiple lines so -> re.DOTALL
            matching_reg = re.compile("({})".format(regex), re.DOTALL)
        else:
            matching_reg = re.compile("({})".format(regex))


        # Because we have 3 parenthesis groups in regex, we obtain a list of tuples and we just want the first
        # element of each tuple and put it in a list.
        return [x[0] for x in matching_reg.findall(content)]


    def _decode_html(self, html, page_id, extra_attr):
        """
        Decode HTML
        """
        # We just call parent class func 
        return self._decode_url(html, page_id, extra_attr)


    def _fix_encoded_html(self, html, page_id, extra_attr):
        """
        Fix an encoded. 
        1. Decode URL
        2. Encode it into Gutenberg style
        """
        fixed_html = unquote(html)

        fixed_html = self._handle_html(fixed_html, page_id, {})

        return fixed_html
    

    def _remove_new_lines(self, html, page_id, extra_attr):
        """
        Remove new lines in html
        """
        return html.replace("\n", "")
    

    def _transform_to_block_with_content(self, call, block_name, content_attribute):
        """ 
        Take a block call <!-- wp:epfl/<block_name> {...} /-->
        and transforms it to a block call with content.

        <!-- wp:epfl/<block_name> {...} -->
        <div class="wp-block-epfl-<block_name>"><!-- wp:freeform -->
        ...
        <!-- /wp:freeform --></div>
        <!-- /wp:epfl/<block_name> -->
        
        We use "wp:freeform" block to put all HTML. This is a "classic editor" block. If you go
        to see page code in the editor, you won't see this block because it is automatically 
        hidden by WordPress and only HTML code is displayed.

        :param call: Block call to modify
        :param block_name: Name of block 
        :param content_attribute: name of block attribute containing content to put inside the block
        """
        block_content = self._get_attribute(call, content_attribute)

        # If attribute is not set, we set it to empty
        if block_content is None:
            block_content = ""

        block_content = self._decode_unicode(block_content)

        call = call.replace("/-->", "-->")

        # We remove new line characters in code
        block_content = block_content.replace('\\n', "").replace('\\r', "").replace("\\t", "")
        # We unescape double quotes
        block_content = block_content.replace('\\"', '"')
        # We remove \n at beginning and end
        call = call.strip("\n")
        block_content = block_content.strip("\n")

        # If block is empty, we have to return without wp:freeform otherwise content won't be editable in visual
        if block_content == "":
            return '{0}\n<div class="wp-block-epfl-{1}"></div>\n<!-- /wp:epfl/{1} -->'.format(call, block_name)

        return '{0}\n<div class="wp-block-epfl-{1}"><!-- wp:tadv/classic-paragraph -->\n{2}\n<!-- /wp:tadv/classic-paragraph --></div>\n<!-- /wp:epfl/{1} -->'.format(call, block_name, block_content)


    def _decode_unicode(self, encoded_html):
        """
        Decode HTML tags to replace unicode characters with decoded characters

        :param encoded_html: HTML to decode
        """

        unicode_reg = re.compile('\\\\u[\w\d]{4,4}')

        # Searching all unicode characters 
        for unicode_chr in list(set(unicode_reg.findall(encoded_html))):
            # Findind decoded character: \\u00e9 -> 00e9
            decoded_chr = unicode_chr.replace('\\u', '')
            # 00e9 -> hex to int -> to char
            decoded_chr = chr(int(decoded_chr, 16))
            # Replacing in string
            encoded_html = encoded_html.replace(unicode_chr, decoded_chr)

        return encoded_html


    def __fix_attributes(self, call, block_name, attributes_desc, page_id):
        """
        Updates 'attributes' parameter (dict) with correct value depending on each attribute description
        contained in 'attributes_desc' parameter.
        If value is not found in shortcode call, it won't be added in Gutenberg block.
        If value is an integer, it will be set as an integer and not a string
        :param call: String with shortcode call
        :param block_name: Name of block we are handling
        :param attributes_desc: List with either attributes names (string) or dict with information to
                    get correct value. Informations can be:
                    'attr_name'     -> (mandatory) Attribute name
                    'func_list'     -> (mandatory) List of functions to apply to attribute value

                    ** Only one of the following optional key can be present in the same time **
                    'with_quotes'   -> (optional) to tell if value has to be stored between quotes

        :param attributes_desc: Dictionnary describing shortcode attributes and how to translate them to a Gutenberg block
        :param page_id: Id of page on which we currently are

        Return: new call value
        """

        new_call = call

        for attr_desc in attributes_desc:

            # Recovering source value
            value = self._get_attribute(call, attr_desc['attr_name'])
            
            # If no value found
            if value is None:
                # we continue to next attribute
                continue
            
            final_value = value
            # Looping through func to apply
            for func_name in attr_desc['func_list']:

                # Looking for function to apply
                map_func = getattr(self, func_name)

                extra_attr = {}

                # Determine if we have to use quotes or not for replacement
                with_quotes = attr_desc['with_quotes'] if 'with_quotes' in attr_desc else True

                final_value = map_func(final_value, page_id, extra_attr)

            new_call = self._change_attribute_value(new_call, block_name, attr_desc['attr_name'], final_value, with_quotes)
        
        return new_call

       
    def _fix_block_contact(self, content, page_id):
        """
        Fix EPFL Contact by putting an attribute content inside the block
        :param content: content to update
        :param page_id: Id of page containing content
        """
        
        block_name = "contact"

        # Looking for all calls to modify them one by one
        calls = self._get_all_block_calls(content, block_name, with_content=True)

        for call in calls:

            new_call = self._remove_block_call_content(call, block_name)

            new_call = self._transform_to_block_with_content(new_call, block_name, "introduction")
            
            if new_call != call:
                self._log_to_file("Before: {}".format(call))
                self._log_to_file("After: {}".format(new_call))

                self._update_report(block_name)

                content = content.replace(call, new_call)
        
        return content


    def _fix_block_scheduler(self, content, page_id):
        """
        Fix EPFL Scheduler by putting an attribute content inside the block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        
        block_name = "scheduler"

        # Looking for all calls to modify them one by one
        calls = self._get_all_block_calls(content, block_name, with_content=True)

        for call in calls:

            new_call = self._remove_block_call_content(call, block_name)

            new_call = self._transform_to_block_with_content(new_call, block_name, "content")
            
            if new_call != call:
                self._log_to_file("Before: {}".format(call))
                self._log_to_file("After: {}".format(new_call))

                self._update_report(block_name)

                content = content.replace(call, new_call)
        
        return content
    

    def _fix_block_toggle(self, content, page_id):
        """
        Fix EPFL Toggle by putting an attribute content inside the block

        :param content: content to update
        :param page_id: Id of page containing content
        """
        
        block_name = "toggle"

        # Looking for all calls to modify them one by one
        calls = self._get_all_block_calls(content, block_name, with_content=True)

        for call in calls:

            new_call = self._remove_block_call_content(call, block_name)

            new_call = self._transform_to_block_with_content(new_call, block_name, "content")
            
            if new_call != call:
                self._log_to_file("Before: {}".format(call))
                self._log_to_file("After: {}".format(new_call))

                self._update_report(block_name)

                content = content.replace(call, new_call)
        
        return content
