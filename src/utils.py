"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import csv
import logging
import os


class Utils:

    @staticmethod
    def csv_to_dict(file_path, delimiter=','):

        sites = []
        with open(file_path) as csvfile:
            reader = csv.DictReader(csvfile, delimiter=delimiter)
            for row in reader:
                sites.append(row)
        return sites

    @staticmethod
    def get_optional_env(key, default):
        """
        Return the value of an optional environment variable, and use
        the provided default if it's not set.
        """
        if not os.environ.get(key):
            logging.warning("The optional environment variable %s is not set, using '%s' as default" % (key, default))

        return os.environ.get(key, default)

    @staticmethod
    def get_mandatory_env(key):

        if not os.environ.get(key):
            msg = "The mandatory environment variable {} is not set".format(key)
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

        logging.basicConfig()
        logger = logging.getLogger()
        logger.setLevel(level)

        # set up logging to file
        fh = logging.FileHandler(Utils.get_optional_env('LOGGING_FILE', 'jahia2wp.log'))
        fh.setLevel(level)
        formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
        fh.setFormatter(formatter)

        # add the handlers to the logger
        logger.addHandler(fh)
