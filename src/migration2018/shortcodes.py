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

    def get_details(self, path, shortcode_list):
        """
        Locate all instance of given shortcode in a given path. Go through all WordPress installs and parse pages to
        extract shortcode details
        :param path: path where to start search
        :param shortcode_list: list with shortcode name to look for
        :return: Dict - Key is WP site URL and value is a list of dict containing shortcode infos.
        """

        # Building "big" regex to match all given shortcodes
        regexes = []
        for shortcode in shortcode_list:
            regexes.append('\[{}\s?.*?\]'.format(shortcode))

        regex = re.compile('|'.join(regexes), re.VERBOSE)

        shortcode_details = {}

        for site_details in WPConfig.inventory(path):

            if site_details.valid == settings.WP_SITE_INSTALL_OK:

                logging.info("Checking %s...", site_details.url)

                try:
                    # Getting site posts
                    post_ids = Utils.run_command("wp post list --post_type=page --format=csv --fields=ID "
                                                 "--skip-plugins --skip-themes --path={}".format(site_details.path))

                    if not post_ids:
                        continue

                    post_ids = post_ids.split('\n')[1:]

                    # Looping through posts
                    for post_id in post_ids:
                        content = Utils.run_command("wp post get {} --field=post_content --skip-plugins --skip-themes "
                                                    "--path={}".format(post_id, site_details.path))

                        # Looking for given shortcode in current post
                        for shortcode_with_args in re.findall(regex, content):

                            if site_details.path not in shortcode_details:
                                shortcode_details[site_details.path] = []

                            post_url = '{}/wp-admin/post.php?post={}&action=edit'.format(site_details.url, post_id)
                            shortcode_details[site_details.path].append({'post_url': post_url,
                                                                         'shortcode_call': shortcode_with_args})

                except Exception as e:
                    logging.error("Error, skipping to next site: %s", str(e))
                    pass

        return shortcode_details

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

                try:

                    # Getting site posts
                    post_ids = Utils.run_command("wp post list --post_type=page --format=csv --fields=ID "
                                                 "--skip-plugins --skip-themes --path={}".format(site_details.path))

                except Exception as e:
                    logging.error("Error getting page list, skipping to next site: %s", str(e))
                    continue

                # Getting list of registered shortcodes to be sure to list only registered and not all strings
                # written between [ ]
                registered_shortcodes = self._get_site_registered_shortcodes(site_details.path)

                if not post_ids:
                    continue

                post_ids = post_ids.split('\n')[1:]

                logging.debug("%s pages to analyze...", len(post_ids))

                # Looping through posts
                for post_id in post_ids:
                    try:
                        content = Utils.run_command("wp post get {} --field=post_content --skip-plugins --skip-themes "
                                                    "--path={}".format(post_id, site_details.path))
                    except Exception as e:
                        logging.error("Error getting page, skipping to next page: %s", str(e))
                        continue

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
        matching_reg = re.compile('(?P<before> \[{}.+ ){}(=(".*?"|\S+?)|\s|\])?'.format(shortcode_name, attr_name),
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

    def __remove_shortcode(self, content, shortcode_name, remove_content=False):
        """
        Completely remove a shortcode (begin and end tag) and even its content if needed

        :param content: string in which doing replacement
        :param shortcode_name: Shortcode name to remove
        :param remove_content: True|False to tell if we also have to remove content or not.
        :return:
        """

        reg_list = []

        if remove_content:
            # To remove [shortcode_name param="2" ...]...[/shortcode_name]
            reg_list.append(re.compile('\[{0} .*?\].*?\[\/{0}\]'.format(shortcode_name), re.VERBOSE))

        else:  # We have to remove only surrounding elements

            # To remove [shortcode_name param="2" ...]
            reg_list.append(re.compile('\[{} .*?\]'.format(shortcode_name), re.VERBOSE))

            # To remove [/shortcode_name]
            reg_list.append(re.compile('\[\/{}]'.format(shortcode_name), re.VERBOSE))

        for reg in reg_list:
            content = reg.sub('', content)

        return content

    def __get_attribute(self, shortcode_call, attr_name):
        """
        Return attribute value (or None if not found) for a given shortcode call
        :param shortcode_call: String with shortcode call: [my_shortcode attr="1"]
        :param attr_name: Attribute name for which we want the value
        :return:
        """
        matching_reg = re.compile('{}=(".+?"|\S+?)'.format(attr_name),
                                  re.VERBOSE)

        value = matching_reg.findall(shortcode_call)
        # We remove surrounding " if exists.
        return value[0].strip('"') if value else None

    def __get_content(self, shortcode_call):
        """
        Return content (or None if not found) for a given shortcode call. This also works for nested shortcodes
        :param shortcode_call: String with shortcode call: [my_shortcode attr="1"]content[/my_shortcode]
        :return:
        """
        # re.DOTALL is to match all characters including \n
        matching_reg = re.compile('\](.*)\[\/', re.DOTALL)

        value = matching_reg.findall(shortcode_call)
        # We remove surrounding " if exists.
        return value[0] if value else None

    def __change_content(self, shortcode_call, new_content):
        """
        Return shortcode call with its content changed by new_content parameter
        :param shortcode_call: String with shortcode call: [my_shortcode attr="1"]content[/my_shortcode]
        :param new_content: Content to put
        :return:
        """
        matching_reg = re.compile('\](.*)\[\/', re.DOTALL)

        return matching_reg.sub(']{}[/'.format(new_content), shortcode_call)

    def __get_all_shortcode_calls(self, content, shortcode_name, with_content=False):
        """
        Look for all calls for a given shortcode in given content
        :param content: String in which to look for shortcode calls
        :param shortcode_name: shortcode name to look for
        :param with_content: To tell if we have to return content as well. If given and shortcode doesn't have content,
        it won't be returned
        :return:
        """
        regex = '\[{}(\s.*?)?\]'.format(shortcode_name)
        if with_content:
            regex += '.*?\[\/{}\]'.format(shortcode_name)

        # re.DOTALL is to match all characters including \n
        matching_reg = re.compile("({})".format(regex), re.DOTALL)

        # Because we have 2 parenthesis groups in regex, we obtain a list of tuples and we just want the first
        # element of each tuple and put it in a list.
        return [x[0] for x in matching_reg.findall(content)]

    def __fix_to_epfl_video(self, content, old_shortcode):
        """
        Fix given video shortcode
        :param content:
        :return:
        """
        new_shortcode = 'epfl_video'

        # a lot of attributes are useless so we try to remove all of them
        content = self.__remove_attribute(content, old_shortcode, 'height')
        content = self.__remove_attribute(content, old_shortcode, 'width')
        content = self.__remove_attribute(content, old_shortcode, 'autoplay')
        content = self.__remove_attribute(content, old_shortcode, 'class')
        content = self.__remove_attribute(content, old_shortcode, 'responsive')

        content = self.__rename_shortcode(content, old_shortcode, new_shortcode)
        return content

    def _fix_su_vimeo(self, content):
        """
        Fix "su_vimeo" from Shortcode ultimate plugin
        :param content:
        :return:
        """
        return self.__fix_to_epfl_video(content, 'su_vimeo')

    def _fix_su_youtube(self, content):
        """
        Fix "su_youtube" from Shortcode ultimate plugin
        :param content:
        :return:
        """
        return self.__fix_to_epfl_video(content, 'su_youtube')

    def _fix_su_youtube_advanced(self, content):
        """
        Fix "su_youtube_advanced" from Shortcode ultimate plugin
        :param content:
        :return:
        """
        return self.__fix_to_epfl_video(content, 'su_youtube_advanced')

    def _fix_epfl_people(self, content):
        """
        Fix all epfl_people shortcodes in content
        :param content:
        :return:
        """

        old_shortcode = 'epfl_people'
        new_shortcode = 'epfl_people_2018'

        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, old_shortcode)

        for call in calls:
            # We generate new shortcode from scratch
            new_call = '[{}]'.format(new_shortcode)

            # Extracing URL in which parameters we are looking for are
            api_url = self.__get_attribute(call, 'url')

            # Trying to find a 'scipers' parameter
            scipers = Utils.get_parameter_from_url(api_url, 'scipers')

            if scipers:
                new_call = self.__add_attribute(new_call, new_shortcode, 'scipers', scipers)

            # Trying to find a 'unit' parameter
            unit = Utils.get_parameter_from_url(api_url, 'unit')

            if unit:
                new_call = self.__add_attribute(new_call, new_shortcode, 'unit', unit)

            # Replacing in global content
            content = content.replace(call, new_call)

        return content

    def _fix_epfl_news(self, content):
        """
        Fix all epfl_news shortcodes in content
        :param content:
        :return:
        """

        old_shortcode = 'epfl_news'
        new_shortcode = 'epfl_news_2018'

        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, old_shortcode)

        nb_news_from_template = {'4': '3',
                                 '8': '5',
                                 '6': '3',
                                 '3': '3',
                                 '2': '3',
                                 '10': '3',
                                 '7': '3',
                                 '1': '3'}

        for call in calls:
            new_call = '[{}]'.format(new_shortcode)

            template = self.__get_attribute(call, 'template')

            # New template is always the same
            new_call = self.__add_attribute(new_call, new_shortcode, 'template', '1')

            nb_news = nb_news_from_template[template] if template in nb_news_from_template else '3'

            new_call = self.__add_attribute(new_call, new_shortcode, 'nb_news', nb_news)

            # Add default parameter
            new_call = self.__add_attribute(new_call, new_shortcode, 'all_news_link', 'true')

            # Replacing in global content
            content = content.replace(call, new_call)

        return content

    def _fix_to_gallery(self, content, old_shortcode):
        """
        Fix all su slider shortcodes in content
        :param content: String in which to fix
        :param old_shortcode: Shortcode name to look for.
        :return:
        """

        new_shortcode = 'gallery'

        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, old_shortcode)

        for call in calls:
            # We generate new shortcode from scratch
            new_call = '[{}]'.format(new_shortcode)

            # SOURCE -> IDS
            source = self.__get_attribute(call, 'source')

            # If not images, it is not supported, we skip it
            if not source.startswith('media:'):
                continue

            ids = source.replace('media:', '').strip()

            # If ids were found,
            if ids:
                new_call = self.__add_attribute(new_call, new_shortcode, 'ids', ids)

                # LINK
                link = self.__get_attribute(call, 'link')
                # 'image' is for su_slider, su_custom_gallery and su_carousel
                # 'lightbox' is for su_custom_gallery and su_carousel
                if link == 'image' or link == 'lightbox':
                    link = 'file'
                # '' is for su_slider
                # 'post', 'attachement' is for su_custom_gallery and su_carousel
                elif link == '' or link == 'post' or link == 'attachement':
                    link = 'attachement'
                else:  # None
                    link = 'none'

                new_call = self.__add_attribute(new_call, new_shortcode, 'link', link)

            else:  # No ids found.
                new_call = ''

            # Replacing in global content
            content = content.replace(call, new_call)

        return content

    def _fix_su_carousel(self, content):
        """
        Fix all su_custom_gallery shortcodes in content
        :param content: String in which to fix
        :return:
        """
        return self._fix_to_gallery(content, 'su_carousel')

    def _fix_su_custom_gallery(self, content):
        """
        Fix all su_custom_gallery shortcodes in content
        :param content: String in which to fix
        :return:
        """
        return self._fix_to_gallery(content, 'su_custom_gallery')

    def _fix_su_slider(self, content):
        """
        Fix all su_slider shortcodes in content
        :param content: String in which to fix
        :return:
        """
        return self._fix_to_gallery(content, 'su_slider')

    def __fix_to_epfl_toggle(self, content, old_shortcode):
        """
        Fix all su_expand and my_buttonexpand to epfl_toggle
        :param content: String in which to fix
        :return:
        """
        new_shortcode = 'epfl_toggle'

        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, old_shortcode, with_content=True)

        for call in calls:
            # getting future toggle content
            toggle_content = self.__get_content(call)

            title = self.__get_attribute(call, 'more_text')

            # We generate new shortcode from scratch
            new_call = '[{0} title="{1}" state="close"]{2}[/{0}]'.format(new_shortcode, title, toggle_content)

            # Replacing in global content
            content = content.replace(call, new_call)

        return content

    def _fix_su_expand(self, content):
        """
        Fix "su_expand" from Shortcode ultimate plugin
        :param content: String in which to fix
        :return:
        """
        return self.__fix_to_epfl_toggle(content, 'su_expand')

    def _fix_my_buttonexpand(self, content):
        """
        Fix "my_buttonexpand" (renamed su_expand) from Shortcode ultimate plugin
        This plugin name is only used on UC website...
        :param content: String in which to fix
        :return:
        """
        return self.__fix_to_epfl_toggle(content, 'my_buttonexpand')

    def _fix_su_accordion(self, content):
        """
        Fix "su_accordion" from Shortcode ultimate plugin. This shortcode is a container for "su_spoiler" elements
        :param content: String in which to fix
        :return:
        """
        old_shortcode = 'su_accordion'

        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, old_shortcode, with_content=True)

        for call in calls:
            # getting future toggle content
            toggle_content = self.__get_content(call)

            # Replacing in global content. In fact, we just remove surrounding shortcode
            content = content.replace(call, toggle_content)

        return content

    def _fix_su_spoiler(self, content):
        """
        Fix "su_spoiler" from Shortcode Ultimate plugin. This shortcode is surrouned by "su_accordion" shortcode and
        is just translated to "epfl_toggle"
        :param content:
        :return:
        """
        old_shortcode = 'su_spoiler'
        new_shortcode = 'epfl_toggle'

        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, old_shortcode, with_content=True)

        for call in calls:
            # getting future toggle content
            toggle_content = self.__get_content(call)

            title = self.__get_attribute(call, 'title')
            is_open = self.__get_attribute(call, 'open')

            state = 'close' if not is_open or is_open == 'no' else 'open'

            # We generate new shortcode from scratch
            new_call = '[{0} title="{1}" state="{2}"]{3}[/{0}]'.format(new_shortcode, title, state, toggle_content)

            # Replacing in global content
            content = content.replace(call, new_call)

        return content

    def __fix_to_atom_button(self, content, old_shortcode):
        """
        Return HTML code to display button according 2018 styleguide:
        https://epfl-idevelop.github.io/elements/#/atoms/button
        :param content: String in which to fix
        :return:
        """
        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, old_shortcode, with_content=True)

        for call in calls:
            text = self.__get_content(call)
            url = self.__get_attribute(call, 'url')
            target = self.__get_attribute(call, 'target')

            html = ''

            if url:
                target = 'target="{}"'.format(target) if target else ""

                html += '<a href="{}" {}>'.format(url, target)

            html += '<button class="btn btn-primary">{}</button>'.format(text)

            if url:
                html += '</a>'

            # Replacing in global content
            content = content.replace(call, html)

        return content

    def _fix_su_button(self, content):
        """
        Fix "su_button" from Shortcode Ultimate plugin.
        :param content: String in which to fix
        :return:
        """
        return self.__fix_to_atom_button(content, 'su_button')

    def _fix_my_buttonbutton(self, content):
        """
        Fix "my_buttonbutton" (renamed su_button) from Shortcode ultimate plugin
        This plugin name is only used on UC website...
        :param content: String in which to fix
        :return:
        """
        return self.__fix_to_atom_button(content, 'my_buttonbutton')

    def _fix_su_divider(self, content):
        """
        Fix "su_divider" from Shortcode Ultimate. We replace it with HTML code, not with another shortcode.
        https://epfl-idevelop.github.io/elements/#/atoms/separator
        :param content: String in which to fix.
        :return:
        """
        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, 'su_divider')

        for call in calls:
            html = '<hr class="bold">'

            # Replacing in global content
            content = content.replace(call, html)

        return content

    def _fix_su_box(self, content):
        """
        Fix "su_box" from Shortcode Ultimate. We replace it with epfl_card
        :param content: String in which to fix.
        :return:
        """
        old_shortcode = 'su_box'
        new_shortcode = 'epfl_card'

        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, old_shortcode, with_content=True)

        for call in calls:

            box_content = self.__get_content(call)

            title = self.__get_attribute(call, 'title')

            new_call = '[{0} title="{1}"]{2}[/{0}]'.format(new_shortcode, title, box_content)

            # Replacing in global content
            content = content.replace(call, new_call)

        return content

    def _fix_epfl_snippets(self, content):
        """
        Fix "epfl_snippets" shortcode and transform it into epfl_card
        :param content: String in which to fix
        :return:
        """
        old_shortcode = 'epfl_snippets'
        new_shortcode = 'epfl_card'

        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, old_shortcode, with_content=True)

        for call in calls:

            new_call = call

            # Delete unused attributes
            new_call = self.__remove_attribute(new_call, old_shortcode, 'subtitle')
            new_call = self.__remove_attribute(new_call, old_shortcode, 'big_image')
            new_call = self.__remove_attribute(new_call, old_shortcode, 'enable_zoom')

            new_call = self.__rename_shortcode(new_call, old_shortcode, new_shortcode)

            # Replacing in global content
            content = content.replace(call, new_call)

        return content

    def _fix_su_quote(self, content):
        """
        Return HTML code to display quote according 2018 styleguide (but without an image and using "col-md-12" instead
        of "col-md-10" for the width):
        https://epfl-idevelop.github.io/elements/#/molecules/quote
        :param content: String in which to fix
        :return:
        """
        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, 'su_quote', with_content=True)

        for call in calls:
            philosophical_thing = self.__get_content(call)
            great_person_who_said_this = self.__get_attribute(call, 'cite')

            html = '<div class="row">'
            html += '<blockquote class="blockquote mt-3 col-md-12 border-0">'
            html += '<p class="mb-0">{}</p>'.format(philosophical_thing)
            html += '<footer class="blockquote-footer">{}</footer>'.format(great_person_who_said_this)
            html += '</blockquote>'
            html += '</div>'

            # Replacing in global content
            content = content.replace(call, html)

        return content

    def _fix_su_list(self, content):
        """
        Return HTML code to display information correctly. We remove surrounding shortcode and add a <br> at the end.
        :param content: String in which to fix
        :return:
        """
        # Looking for all calls to modify them one by one
        calls = self.__get_all_shortcode_calls(content, 'su_list', with_content=True)

        for call in calls:
            list_content = self.__get_content(call)

            html = '{}<br>'.format(list_content)

            # Replacing in global content
            content = content.replace(call, html)

        return content

    def _fix_epfl_memento(self, content):
        """
        Fix "epfl_memento" shortcode
        :param content:
        :return:
        """
        old_shortcode = 'epfl_memento'
        new_shortcode = 'epfl_memento_2018'

        # a lot of attributes are useless so we try to remove all of them
        content = self.__change_attribute_value(content, old_shortcode, 'template', '4')

        content = self.__rename_shortcode(content, old_shortcode, new_shortcode)
        return content

    def fix_site(self, openshift_env, wp_site_url):
        """
        Fix shortocdes in WP site
        :param openshift_env: openshift environment name
        :param wp_site_url: URL to website to fix.
        :return: dictionnary with report.
        """

        content_filename = Utils.generate_name(15, '/tmp/')

        report = {}

        wp_site = WPSite(openshift_env, wp_site_url)
        wp_config = WPConfig(wp_site)

        if not wp_config.is_installed:
            logging.info("No WP site found at given URL (%s)", wp_site_url)
            return report

        logging.info("Fixing %s...", wp_site.path)

        # Getting list of registered shortcodes to be sure to list only registered and not all strings
        # written between [ ]
        registered_shortcodes = self._get_site_registered_shortcodes(wp_site.path)

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
            original_content = content

            # Looking for all shortcodes in current post
            for shortcode in list(set(re.findall(self.regex, content))):

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

                content = fixed_content

            # If content changed for current page,
            if content != original_content:

                logging.debug("Content fixed, updating page...")

                for try_no in range(settings.WP_CLI_AND_API_NB_TRIES):
                    try:
                        # We use a temporary file to store page content to avoid to have problems with simple/double
                        # quotes and content size
                        with open(content_filename, 'wb') as content_file:
                            content_file.write(content.encode())
                        wp_config.run_wp_cli("post update {} --skip-plugins --skip-themes {} ".format(
                            post_id, content_filename))

                    except Exception as e:
                        if try_no < settings.WP_CLI_AND_API_NB_TRIES - 1:
                            logging.error("fix_site() error. Retry %s in %s sec...",
                                          try_no + 1,
                                          settings.WP_CLI_AND_API_NB_SEC_BETWEEN_TRIES)
                            time.sleep(settings.WP_CLI_AND_API_NB_SEC_BETWEEN_TRIES)
                            pass

        # Cleaning
        if os.path.exists(content_filename):
            os.remove(content_filename)

        return report
