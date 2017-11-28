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


def deprecated(message):
    """ This is a decorator which can be used to mark functions as deprecated. It will result in a
        warning being emmitted when the function is used.
        https://stackoverflow.com/questions/2536307/decorators-in-the-python-standard-lib-deprecated-specifically

        # Examples

        @deprecated
        def some_old_function(x,y):
            return x + y

        class SomeClass:
            @deprecated
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

    @staticmethod
    def run_command(command, encoding=sys.stdout.encoding):
        try:
            # encode command properly for subprocess
            command_bytes = command.encode(encoding)
            # run command and log output
            proc = subprocess.run(command_bytes, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True, shell=True)
            logging.debug("%s => %s", command, proc.stdout)
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
            return False

    @classmethod
    def csv_stream_do_dict(cls, stream, delimiter=','):
        """
        Transform Stream (in CSV format) into a dictionnary

        Arguments keywords:
        stream -- stream containing infos to put in dictionnary
                  For stream information, have a look here: https://docs.python.org/3.5/library/io.html
        delimiter -- character to use to split infos coming from stream (CSV)

        Return: list of dictionnaries
        """
        rows = []
        reader = csv.DictReader(stream, delimiter=delimiter)
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
            return cls.csv_stream_do_dict(stream, delimiter=delimiter)

    @classmethod
    def csv_filepath_to_dict(cls, file_path, delimiter=',', encoding="utf-8"):
        """
        Returns the rows of the given CSV file as a list of dicts

        Arguments keywords:
        file_path -- path to file containing infos (in CSV format)
        delimiter -- character to use to split infos coming from file (CSV)
        encoding -- encoding used in file 'file_path'

        Retur: list of dictionnaries
        """
        with open(file_path, 'r', encoding=encoding) as stream:
            return cls.csv_stream_do_dict(stream, delimiter=delimiter)

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
                "The optional environment variable %s is not set, using '%s' as default", key, default)

        return os.environ.get(key, default)

    @classmethod
    def get_mandatory_env(cls, key):
        """
        Return the value of a mandatory environment variable. If the variable doesn't exists, exception is raised.

        Arguments keywords:
        key -- Name of mandatory variable we want to get the value
        """
        if not os.environ.get(key):
            msg = "The mandatory environment variable {} is not set".format(
                key)
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
        command = "tar --create --file={} --listed-incremental={} {}".format(
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
