"""All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
manage_redirections: an amazing tool !

Usage:
  manage_redirections.py copy-jahia-redirections <source_site_url> <destination_site_url> [--debug | --quiet]
  manage_redirections.py copy-jahia-redirections-many <csv_file> [--debug | --quiet]
  manage_redirections.py update-redirections <site_url> [--debug | --quiet]
  manage_redirections.py update-redirections-many <csv_file> [--debug | --quiet]

Options:
  -h --help                 Show this screen.
  -v --version              Show version.
  --debug                   Set log level to DEBUG [default: INFO]
  --quiet                   Set log level to WARNING [default: INFO]
"""
import os
import logging
from docopt import docopt
from docopt_dispatch import dispatch
from ops import SshRemoteSite
from utils import Utils
os.environ['WP_ENV'] = 'manage redirections'  # noqa
from settings import VERSION


WP_REDIRECTS_AFTER_VENTILATION = "WordPress-Redirects-After-Ventilation"


def extract_htaccess_part(content, marker):
    """
    Extract htaccess part between start and end marker.

    :param content: content of htaccess file
    :param marker: each WordPress htaccess contains part which define by marker
    :return: the "right" part of htaccess file
    """
    start_marker = "# BEGIN {}".format(marker)
    end_marker = "# END {}".format(marker)
    result = ""

    if start_marker not in content and end_marker not in content:
        # This marker is untraceable but it's probably normal. Example: 'Jahia-Files-Redirect'
        logging.debug("The marker {} is untraceable in htaccess content {}".format(marker, content))
    elif start_marker not in content:
        error_msg = "Error during extract_htaccess_part: start marker {} not in content {}".format(
            start_marker,
            content
        )
        logging.error(error_msg)
    elif end_marker not in content:
        error_msg = "Error during extract_htaccess_part: end marker {} not in content {}".format(
            end_marker,
            content
        )
        logging.error(error_msg)
    else:
        result = (content.split(start_marker))[1].split(end_marker)[0]
        result = "\n".join([start_marker, result, end_marker])
    return result


def get_jahia_redirections(content):
    """
    Extract and return jahia redirections from htaccess content

    :param content: htaccess content (string)
    :return: jahia redirections
    """
    jahia_page_redirect = extract_htaccess_part(content, "Jahia-Page-Redirect")
    jahia_files_redirect = extract_htaccess_part(content, "Jahia-Files-Redirect")
    jahia_redirect = "\n".join([jahia_page_redirect, jahia_files_redirect])
    logging.debug("Jahia redirections:\n{}".format(jahia_redirect))

    return jahia_redirect


def _copy_jahia_redirections(source_site_url, destination_site_url):
    """
    1. Connect in SSH to the server of source site
    2. Read htaccess file from source site
    3. Extract jahia redirections from htaccess file
    4. Connect in SSH to the server of destination site
    5. Read htaccess file from destination site
    6. Insert jahia redirections at the begining of htaccess file from destination site
    """

    # retrieve the content of the htaccess file from the source site
    source_site = SshRemoteSite(source_site_url)
    source_site_content = source_site.get_htaccess_content()

    # if source_site comes from test infra, we need to delete the site name inside all 301 jahia redirections
    if source_site_url.startswith("https://migration-wp.epfl.ch/"):

        # prepare the search and replace '/<site/' by '/'
        if not source_site.wp_path.startswith('/'):
            source_site.wp_path = '/' + source_site.wp_path

        # search and replace '/<site/' by '/' in htaccess content
        source_site_content = source_site_content.replace(source_site.wp_path, "/")
        logging.debug("Rename all 301 jahia redirections without then site name: {}".format(source_site_content))

    # extract jahia rules
    jahia_redirections_content = get_jahia_redirections(source_site_content)

    # retrieve the content of the htaccess file from the destination site
    destination_site = SshRemoteSite(destination_site_url)
    destination_site_content = destination_site.get_htaccess_content()

    # insert jahia rules
    new_content = "\n".join([jahia_redirections_content, destination_site_content])
    destination_site.write_htaccess_content(new_content)


def _update_redirections(site_url):
    """
    Update redirections.
    In other words, we replace the content of the htaccess file with a 302 rule like :
    RewriteRule ^(.*)$ https://dcsl.epfl.ch$1 [L,QSA,R=301]
    """

    site = SshRemoteSite(site_url)

    new_content = "# BEGIN {}".format(WP_REDIRECTS_AFTER_VENTILATION),
    new_content += "RewriteRule ^(.*)$ {}$1 [L,QSA,R=301]".format(site_url),
    new_content += "# END {}".format(WP_REDIRECTS_AFTER_VENTILATION)

    site.write_htaccess_content(new_content)


@dispatch.on('copy-jahia-redirections')
def copy_jahia_redirections(source_site_url, destination_site_url, **kwargs):
    """
    1. Connect in SSH to the server of source site
    2. Read htaccess file from source site
    3. Extract jahia redirections from htaccess file
    4. Connect in SSH to the server of destination site
    5. Read htaccess file from destination site
    6. Insert jahia redirections at the begining of htaccess file from destination site
    """
    logging.info("Starting copy jahia redirections from {} to {} ".format(source_site_url, destination_site_url))
    _copy_jahia_redirections(source_site_url, destination_site_url)
    logging.info("End of copy jahia redirections from {} to {} ".format(source_site_url, destination_site_url))


@dispatch.on('copy-jahia-redirections-many')
def copy_jahia_redirections_many(csv_file, **kwargs):
    """
    Copy jahia redirections for all sites present in the csv file
    """
    rows = Utils.csv_filepath_to_dict(csv_file)

    logging.info("Starting copy jahia redirections for {} sites".format(len(rows)))
    for index, row in enumerate(rows, start=1):

        source_site_url = row['source_site_url']
        destination_site_url = row['destination_site_url']

        logging.info("Starting site n°{} copy jahia redirections from {} to {}".format(
            index,
            source_site_url,
            destination_site_url)
        )
        _copy_jahia_redirections(source_site_url, destination_site_url)
        logging.info("End site n°{} of copy jahia redirections from {} to {}".format(
            index,
            source_site_url,
            destination_site_url)
        )


@dispatch.on('update-redirections')
def update_redirections(site_url, **kwargs):
    """
    Update redirections.
    In other words, we replace the content of the htaccess file with a 302 rule like :
    RewriteRule ^(.*)$ https://dcsl.epfl.ch$1 [L,QSA,R=301]
    """
    logging.info("Starting update redirections from {} ".format(site_url))
    _update_redirections(site_url)
    logging.info("End of update redirections from {} ".format(site_url))


@dispatch.on('update-redirections-many')
def update_redirections_many(csv_file, **kwargs):
    """
    Update redirections for all sites present in the csv file.
    In other words, we replace the content of the htaccess file with a 302 rule like :
    RewriteRule ^(.*)$ https://dcsl.epfl.ch$1 [L,QSA,R=301]
    """

    rows = Utils.csv_filepath_to_dict(csv_file)
    logging.info("Updating redirections for {} sites".format(len(rows)))
    for index, row in enumerate(rows, start=1):

        site_url = row['site_url']
        logging.info("Updating redirections for site n°{} {}".format(index, site_url))
        _update_redirections(site_url)
        logging.info("End update redirections for site n°{} {}".format(index, site_url))


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
