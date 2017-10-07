"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import logging
import subprocess
import sys
import io
import os
import csv
import string
import binascii
import random


class Utils(object):
    """Generic and all-purpose helpers"""

    @staticmethod
    def run_command(command):
        try:
            # run command and log output
            proc = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True, shell=True)
            logging.debug("%s => %s", command, proc.stdout)
            # return output if got any, True otherwise
            if proc.stdout:
                text = proc.stdout.decode(sys.stdout.encoding)
                # get rid of final spaces, line return
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
        rows = []
        reader = csv.DictReader(stream, delimiter=delimiter)
        for row in reader:
            rows.append(row)
        return rows

    @classmethod
    def csv_string_to_dict(cls, text, delimiter=','):
        with io.StringIO(text) as stream:
            return cls.csv_stream_do_dict(stream, delimiter=delimiter)

    @classmethod
    def csv_filepath_to_dict(cls, file_path, delimiter=','):
        """Returns the rows of the given CSV file as a list of dicts"""
        with open(file_path, 'r', encoding='utf8') as stream:
            return cls.csv_stream_do_dict(stream, delimiter=delimiter)

    @staticmethod
    def get_optional_env(key, default):
        """
        Return the value of an optional environment variable, and use
        the provided default if it's not set.
        """
        if not os.environ.get(key):
            logging.warning(
                "The optional environment variable %s is not set, using '%s' as default", key, default)

        return os.environ.get(key, default)

    @staticmethod
    def get_mandatory_env(key):

        if not os.environ.get(key):
            msg = "The mandatory environment variable {} is not set".format(
                key)
            logging.error(msg)
            raise Exception(msg)

        return os.environ.get(key)

    @staticmethod
    def set_logging_config(args):
        """
        Set logging with the 'good' level
        """
        level = logging.INFO

        if args['--quiet']:
            level = logging.WARNING
        elif args['--debug']:
            level = logging.DEBUG

        # set up logging to console
        logging.basicConfig(format='%(levelname)s - %(funcName)s - %(message)s')
        logger = logging.getLogger()
        logger.setLevel(level)

        # set up logging to file
        fh = logging.FileHandler(Utils.get_optional_env('LOGGING_FILE', 'jahia2wp.log'))
        fh.setLevel(level)
        formatter = logging.Formatter(
            '%(asctime)s - %(levelname)s - %(filename)s:%(lineno)s:%(funcName)s - %(message)s')
        fh.setFormatter(formatter)

        # add the handlers to the logger
        logger.addHandler(fh)

    @staticmethod
    def generate_random_b64(length):
        """
        Generate a random string encoded with base 64
        """
        return binascii.hexlify(os.urandom(int(length / 2))).decode("utf-8")

    @classmethod
    def generate_name(cls, length, prefix=''):
        """ Generate a random alpha-numeric string, starting with alpha charaters """
        seed_length = length - len(prefix) - 1
        first = random.choice(string.ascii_letters)
        return prefix + first + cls.generate_password(seed_length, symbols='')

    @staticmethod
    def generate_password(length, symbols='!@#^&*'):
        """
        Generate a random password
        """
        # the chars we are going to use. We don't use the plus sign (+) because
        # it's problematic in URLs
        chars = string.ascii_letters + string.digits + symbols
        random.seed = (os.urandom(1024))

        return ''.join(random.choice(chars) for i in range(length))
