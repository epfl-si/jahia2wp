""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
jahia2wp: an amazing tool !

Usage:
  jahia2wp.py check-one <wp_env> <wp_url> [--debug | --quiet]
  jahia2wp.py clean-one <wp_env> <wp_url> [--debug | --quiet]
  jahia2wp.py generate-one <wp_env> <wp_url>
                           [--wp-title=<WP_TITLE> --owner=<OWNER_ID> --responsible=<RESPONSIBLE_ID>]
                           [--debug | --quiet]
  jahia2wp.py generate <csv_file> [--output-dir=<OUTPUT_DIR>] [--debug | --quiet]
  jahia2wp.py veritas <path>

Options:
  -h --help                     Show this screen.
  -v --version                  Show version.
"""

import logging

from docopt import docopt
from docopt_dispatch import dispatch

from veritas.veritas import VeritasValidor
from wordpress import WPSite, WPRawConfig, WPGenerator

from settings import VERSION
from utils import Utils


@dispatch.on('check-one')
def check_one(wp_env, wp_url, **kwargs):
    wp_site = WPSite(wp_env, wp_url, wp_default_site_title=kwargs['wp_title'])
    wp_config = WPRawConfig(wp_site)
    if wp_config.is_installed:
        print("WordPress site installed @{}".format(wp_site.path))
    else:
        print("No WordPress site found for {}".format(wp_site.url))


@dispatch.on('clean-one')
def clean_one(wp_env, wp_url, **kwargs):
    wp_generator = WPGenerator(wp_env, wp_url)
    if not wp_generator.wp_config.is_installed:
        print("WordPress site already removed")
    else:
        wp_generator.clean()
        print("Successfully cleaned WordPress site {}".format(wp_generator.wp_site.url))


@dispatch.on('generate-one')
def generate_one(wp_env, wp_url, wp_title=None, owner_id=None, responsible_id=None, **kwargs):
    wp_generator = WPGenerator(wp_env, wp_url, wp_title, owner_id, responsible_id)
    wp_generator.generate()


@dispatch.on('generate')
def generate(csv_file, **kwargs):

    # TODO GC: call veritas validation
    # TODO GC: modify veritas validation to validate row by row
    for row in Utils.csv_to_dict(csv_file):
        wp_generator = WPGenerator(
            row["openshift_env"],
            row["wp_site_url"],
            row["wp_default_site_title"],
            row["owner_id"],
            row["responsible_id"]
        )
        wp_generator.generate()


@dispatch.on('veritas')
def veritas(path, **kwargs):
    validator = VeritasValidor(path)

    validator.validate()

    validator.print_errors()

    return True


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
