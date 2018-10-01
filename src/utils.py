"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import logging
import functools
import subprocess
import importlib
import sys
import io
import os
import csv
import string
import binascii
import random
import xml.dom.minidom
import re
import requests


from urllib.parse import urlsplit
from bs4 import BeautifulSoup


def deprecated(message):
    """ This is a decorator which can be used to mark functions as deprecated. It will result in a
        warning being emmitted when the function is used.
        https://stackoverflow.com/questions/2536307/decorators-in-the-python-standard-lib-deprecated-specifically

        # Examples

        @deprecated
        def some_old_function(x,y):
            return x + y

        class SomeClass:
            @deprecat
            def some_old_method(self, x,y):
                return x + y
    """
    def decorator(func):
        @functools.wraps(func)
        def new_func(*args, **kwargs):
            print("WARNING: Call to deprecated function '{}'. {}".format(func.__name__, message))
            return func(*args, **kwargs)
        return new_func

    return decorator


class Utils(object):
    """Generic and all-purpose helpers"""

    # the cache with all the doms
    dom_cache = {}

    @staticmethod
    def increment_count(dictionary, key):
        """Increments the value of the given key in the given dictionary by one"""
        if key in dictionary:
            dictionary[key] = dictionary[key] + 1
        else:
            dictionary[key] = 1

    @staticmethod
    def get_tag_attribute(dom, tag, attribute):
        """Returns the given attribute of the given tag"""
        elements = dom.getElementsByTagName(tag)

        if not elements:
            return ""

        return elements[0].getAttribute(attribute)

    @staticmethod
    def get_tag_attributes(dom, tag, attribute):
        """Returns the given attributes of the given tag, in an array"""
        elements = dom.getElementsByTagName(tag)

        if not elements:
            return []

        read_elements = []
        for element in elements:
            read_elements.append(element.getAttribute(attribute))

        return read_elements

    @classmethod
    def get_dom(cls, path):
        """Returns the dom of the given XML file path"""

        # we check the cache first
        if path in cls.dom_cache:
            return cls.dom_cache[path]

        # load the xml
        xml_file = open(path, "r", encoding="UTF-8")

        # we use BeautifulSoup first because some XML files are invalid
        xml_soup = BeautifulSoup(xml_file.read(), 'xml')

        dom = xml.dom.minidom.parseString(str(xml_soup))

        # save in the cache
        cls.dom_cache[path] = dom

        return dom

    @staticmethod
    def get_dom_next_level_children(dom, child_name):
        """
        Returns next level children with name=<child_name>
        """

        child_list = []
        for child in dom.childNodes:
            if child.nodeName == child_name:
                child_list.append(child)

        return child_list

    @staticmethod
    def run_command(command, encoding=sys.stdout.encoding):
        """
        Execute the given command in a shell

        Argument keywords
        command -- command to execute
        encoding -- encoding to use

        Return
        False if error
        True if OK but no output from command
        Command output if there is one
        """
        try:
            # encode command properly for subprocess
            command_bytes = command.encode(encoding)

            # run command and log output
            proc = subprocess.run(command_bytes, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True, shell=True)
            logging.debug("%s => %s", command_bytes, proc.stdout)
            # return output if got any, True otherwise
            if proc.stdout:
                # Second parameter "ignore" has been added because some plugins have 'strange' characters in their
                # name so 'decode' is failing and exits the script. Adding "ignore" as parameter prevent script from
                # exiting.
                text = proc.stdout.decode(encoding, "ignore")
                # get rid of final spaces, line return
                logging.debug(text.strip())
                return text.strip()
            return True

        except subprocess.CalledProcessError as err:
            # log error with content of stderr
            logging.error("command failed (code %s) with error <%s> => %s",
                          err.returncode,
                          err,
                          err.stderr)
            raise err

        except Exception as err:
            logging.error("command failed with error %s", err)
            raise err

    @classmethod
    def csv_stream_to_dict(cls, stream, delimiter=','):
        """
        Transform Stream (in CSV format) into a dictionary.

        Arguments keywords:
        stream -- stream containing infos to put in dictionary
                  For stream information, have a look here: https://docs.python.org/3.5/library/io.html
        delimiter -- character to use to split infos coming from stream (CSV)

        Return: list of dictionaries
        """
        rows = []
        # Getting stream content and ignoring lines beginning with # (treated as comment lines)
        reader = csv.DictReader(filter(lambda row: row[0] != '#', stream), delimiter=delimiter)
        for row in reader:
            rows.append(row)
        return rows

    @classmethod
    def csv_string_to_dict(cls, text, delimiter=','):
        """
        Transform a string (in CSV format) into a dictionnary

        Arguments keywords:
        text -- String containing CSV information
        delimiter -- character to use to split infos coming from string (CSV)

        Return: list of dictionnaries
        """
        with io.StringIO(text) as stream:
            return cls.csv_stream_to_dict(stream, delimiter=delimiter)

    @classmethod
    def csv_filepath_to_dict(cls, file_path, delimiter=',', encoding="utf-8"):
        """
        Returns the rows of the given CSV file as a list of dictionaries.

        Arguments keywords:
        file_path -- path to file containing infos (in CSV format)
        delimiter -- character to use to split infos coming from file (CSV)
        encoding -- encoding used in file 'file_path'

        Return: list of dictionaries
        """
        with open(file_path, 'r', encoding=encoding) as stream:
            return cls.csv_stream_to_dict(stream, delimiter=delimiter)

    @classmethod
    def get_optional_env(cls, key, default):
        """
        Return the value of an optional environment variable, and use
        the provided default if it's not set.

        Arguments keywords:
        key -- Name of variable we want to get the value
        default -- Value to return if 'key' not found in environment variables
        """
        if not os.environ.get(key):
            logging.warning(
                "The optional environment variable {} is not set, using '{}' as default".format(key, default))

        return os.environ.get(key, default)

    @classmethod
    def get_mandatory_env(cls, key):
        """
        Return the value of a mandatory environment variable. If the variable doesn't exists, exception is raised.

        Arguments keywords:
        key -- Name of mandatory variable we want to get the value
        """
        if not os.environ.get(key):
            msg = "The mandatory environment variable {} is not set".format(key)
            logging.error(msg)
            raise Exception(msg)

        return os.environ.get(key)

    @classmethod
    def set_logging_config(cls, args):
        """
        Set logging with the 'good' level

        Arguments keywords:
        args -- list containing parameters passed to script
        """
        # get openshift env
        # do not load it from settings, because loading settings will probably make some calls to logging,
        # which will shortcut our call to 'logging.basicConfig' below
        OPENSHIFT_ENV = Utils.get_mandatory_env("WP_ENV")

        # set up level of logging
        level = logging.INFO
        if args['--quiet']:
            level = logging.WARNING
        elif args['--debug']:
            level = logging.DEBUG

        # set up logging to console
        format = '%(levelname)s - {} - %(funcName)s - %(message)s'
        logging.basicConfig(format=format.format(OPENSHIFT_ENV))
        logger = logging.getLogger()
        logger.setLevel(level)

        # set up logging to file
        fh = logging.FileHandler(Utils.get_optional_env('LOGGING_FILE', 'jahia2wp.log'))
        fh.setLevel(level)
        format = '%(asctime)s - %(levelname)s - {} - %(filename)s:%(lineno)s:%(funcName)s - %(message)s'
        formatter = logging.Formatter(format.format(OPENSHIFT_ENV))
        fh.setFormatter(formatter)

        # add the handlers to the logger
        logger.addHandler(fh)

    @staticmethod
    def generate_random_b64(length):
        """
        Generate a random string encoded with base 64

        Arguments keywords:
        length -- length of generated string.
        """
        return binascii.hexlify(os.urandom(int(length / 2))).decode("utf-8")

    @classmethod
    def generate_name(cls, length, prefix=''):
        """
        Generate a random alpha-numeric string, starting with alpha charaters

        Arguments keywords:
        length -- length of generated name
        prefix -- string to put as prefix to generated name
        """
        seed_length = length - len(prefix) - 1
        first = random.choice(string.ascii_letters)
        return prefix + first + cls.generate_password(seed_length, symbols='')

    @staticmethod
    def generate_password(length, symbols='!@#^&*'):
        """
        Generate a random password

        Arguments keywords
        length -- length of generated password
        symbols -- special symbols to add to 'default' characters used to generate the password
        """
        # the chars we are going to use. We don't use the plus sign (+) because
        # it's problematic in URLs
        chars = string.ascii_letters + string.digits + symbols
        random.seed = (os.urandom(1024))

        return ''.join(random.choice(chars) for i in range(length))

    @staticmethod
    def generate_tar_file(tar_file_path, tar_listed_inc_file_path, source_path):
        """
        Generate a tar file

        Arguments keywords
        tar_file_path -- path of TAR file to create
        tar_listed_inc_file_path -- path to file containing incremental infos to help to create tar file
        source_path -- path to infos to put in TAR file
        """
        command = "tar --create --no-check-device --file={} --listed-incremental={} {}".format(
            tar_file_path,
            tar_listed_inc_file_path,
            source_path
        )
        return Utils.run_command(command)

    @staticmethod
    def import_class_from_string(class_string):
        """
        Import (and return) a class from its name

        Arguments keywords:
        class_string -- name of class to import
        """
        module_name, class_name = class_string.rsplit('.', 1)
        module = importlib.import_module(module_name)
        return getattr(module, class_name)

    @staticmethod
    def get_domain(url):
        """
        Return the domain name of url parameter
        """
        return urlsplit(url)[1].split(':')[0]

    @staticmethod
    def insert_in_htaccess(site_root_path, marker, insertion, at_beginning=False):
        """
        Add/update content in .htaccess file. Content is added between BEGIN and END markers (defined with 'marker')

        Inspired by: https://developer.wordpress.org/reference/functions/insert_with_markers/

        Arguments keywords:
        site_root_path -- Path to website root (where .htaccess file is located)
        marker -- String to use to encapsulate content to add
        insertion -- Array with lines to add in .htaccess file

        """
        full_path = os.path.join(site_root_path, ".htaccess")

        if not os.path.isfile(full_path):
            if not os.access(site_root_path, os.W_OK):
                raise Exception("Path not writable: {}".format(site_root_path))
            # Creating empty file
            open(full_path, 'a').close()
        else:
            if not os.access(full_path, os.W_OK):
                raise Exception("File not writable: {}".format(full_path))

        if not isinstance(insertion, list):
            insertion = insertion.split("\n")

        start_marker = "# BEGIN {}".format(marker)
        end_marker = "# END {}".format(marker)

        with open(full_path, 'r+') as fp:

            lines = fp.readlines()

            # Remove \r\n
            lines = [line.rstrip("\r\n") for line in lines]

            # Split out the existing file into the preceding lines, and those that appear after the marker
            pre_lines = []
            post_lines = []
            existing_lines = []
            found_marker = False
            found_end_marker = False

            for line in lines:
                if not found_marker and (line.find(start_marker) != -1):
                    found_marker = True
                    continue

                elif not found_end_marker and (line.find(end_marker) != -1):
                    found_end_marker = True
                    continue

                if not found_marker:
                    pre_lines.append(line)
                elif found_marker and found_end_marker:
                    post_lines.append(line)
                else:
                    existing_lines.append(line)

            # Check to see if there was a change
            if set(existing_lines) != set(insertion):

                if at_beginning and not found_marker:
                    new_file_data = "\n".join([start_marker] + insertion + [end_marker] + pre_lines + post_lines)
                else:
                    new_file_data = "\n".join(pre_lines + [start_marker] + insertion + [end_marker] + post_lines)

                fp.seek(0)
                fp.write(new_file_data)
                fp.truncate(fp.tell())
                fp.flush()

    @staticmethod
    def clean_html_comments(content):
        """
        Clean HTML comments and images base64
        """
        content = re.sub("(<!--.*?-->)", "", content)
        return content

    def is_html(content):
        """
        Return True if the content is HTML.
        """
        return bool(BeautifulSoup(content, "html.parser").find())

    @staticmethod
    def get_redirected_url(url):
        """
        Returns the URL on which HTTP GET is redirected  (can be different URL or simply HTTP to HTTPS)
        :param url: URL we have to check
        :return: URL on which we are redirected.
        """
        url = url.strip()
        if url == "":
            return ""

        # To catch invalid URLs
        try:
            response = requests.get(url)

            # Check for 30x or 200 status code. If condition satisfied, it means it's a redirect
            if 300 <= response.status_code < 400 or response.status_code == 200:
                return response.url

            else:
                # If we cannot get a correct answer, we assume there is no redirect
                return url
        except:
            # URL seems to be invalid but not our problem, so we return it as it is.
            return url

    @staticmethod
    def get_random_string(length):
        """
        Generate a random string of asked length

        :param length:
        :return:
        """
        return ''.join(random.choice(string.ascii_uppercase + string.digits) for _ in range(length))

    @staticmethod
    def handle_custom_chars(html, escape=True):
        """
        Manage some special characters in shortcode attributes values. We have to do this to avoid BeautifulSoup to
        transform HTML entities back to "real" characters.
        When escaped, special characters are replaced by custom identifiers that won't be transformed by BeautifulSoup.
        And when unescaped, quotes are set back to corresponding HTML entities

        For now, we only encode simple/double quotes and brackets. If more special characters needs to be added in the
        future, just do it ;-)

        :param html: string in which (un)escape
        :param escape: To tells if we have to escape or unescape.
        :return:
        """

        # Element to replace: https://www.freeformatter.com/html-entities.html
        # Tuple format :
        # <originalChar>, <customHtmlEntity>, <officialHtmlEntity>
        replace = [('[', '##91!', '&#91;'),
                   (']', '##93!', '&#93;'),
                   ("'", '#apos!', '&apos;'),
                   ('"', '#quot!', '&quot;')]

        for original, escape_to, unescape_to in replace:

            if escape:
                html = html.replace(original, escape_to)
            else:
                html = html.replace(escape_to, unescape_to)

        return html

    @staticmethod
    def escape_quotes(str):
        return str.replace('"', '\\"')
