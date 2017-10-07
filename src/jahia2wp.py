"""All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
jahia2wp: an amazing tool !

Usage:
  jahia2wp.py check-one    <wp_env> <wp_url> [--debug | --quiet]
  jahia2wp.py clean-one    <wp_env> <wp_url> [--debug | --quiet]
  jahia2wp.py generate-one <wp_env> <wp_url>
    [--wp-title=<WP_TITLE> --admin-password=<ADMIN_PASSWORD>]
    [--owner=<OWNER_ID> --responsible=<RESPONSIBLE_ID>]
    [--debug | --quiet]
  jahia2wp.py generate-many <csv_file> [--output-dir=<OUTPUT_DIR>] [--debug | --quiet]
  jahia2wp.py wp-version    <wp_env> <wp_url> [--debug | --quiet]
  jahia2wp.py wp-admins     <wp_env> <wp_url> [--debug | --quiet]
  jahia2wp.py veritas       <csv_file>

Options:
  -h --help                     Show this screen.
  -v --version                  Show version.
"""

import logging

from pprint import pprint
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
    if not wp_config.is_installed:
        raise SystemExit("No files found for {}".format(wp_site.path))
    if not wp_config.is_config_valid:
        raise SystemExit("Configuration not valid for {}".format(wp_site.path))
    if not wp_config.is_install_valid:
        raise SystemExit("Could not login or use site at {}".format(wp_site.url))
    # success case
    print("WordPress site valid and accessible at {}".format(wp_site.url))


@dispatch.on('wp-version')
def wp_version(wp_env, wp_url, **kwargs):
    wp_site = WPSite(wp_env, wp_url, wp_default_site_title=kwargs['wp_title'])
    wp_config = WPRawConfig(wp_site)
    print(wp_config.wp_version.strip())


@dispatch.on('wp-admins')
def wp_admins(wp_env, wp_url, **kwargs):
    wp_site = WPSite(wp_env, wp_url, wp_default_site_title=kwargs['wp_title'])
    wp_config = WPRawConfig(wp_site)
    for admin in wp_config.admins:
        print(admin)


@dispatch.on('clean-one')
def clean_one(wp_env, wp_url, **kwargs):
    wp_generator = WPGenerator(wp_env, wp_url)
    if wp_generator.clean():
        print("Successfully cleaned WordPress site {}".format(wp_generator.wp_site.url))


@dispatch.on('generate-one')
def generate_one(wp_env, wp_url, wp_title=None, admin_password=None, owner_id=None, responsible_id=None, **kwargs):
    wp_generator = WPGenerator(
        wp_env,
        wp_url,
        wp_default_site_title=wp_title,
        admin_password=admin_password,
        owner_id=owner_id,
        responsible_id=responsible_id)
    if not wp_generator.generate():
        raise SystemExit("Generation failed. More info above")

    print("Successfully created new WordPress site at {}".format(wp_generator.wp_site.url))


@dispatch.on('generate-many')
def generate_many(csv_file, **kwargs):

    # use Veritas to get valid rows
    validator = VeritasValidor(csv_file)
    rows = validator.get_valid_rows()

    # print errors
    print("The following lines have errors that prevent the generation of the WP site:\n")
    validator.print_errors()

    # create a new WP site for each row
    print("\n{} websites will now be generated...\n".format(len(rows)))
    for index, row in rows:
        print("Index #{}:\n---".format(index))
        pprint(row)
        WPGenerator(
            row["openshift_env"],
            row["wp_site_url"],
            wp_default_site_title=row["wp_default_site_title"],
            owner_id=row["owner_id"],
            responsible_id=row["responsible_id"],
        ).generate()


@dispatch.on('veritas')
def veritas(csv_file, **kwargs):
    validator = VeritasValidor(csv_file)

    validator.validate()

    validator.print_errors()


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
