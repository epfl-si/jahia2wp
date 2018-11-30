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

    # TODO: tester si le marker n'existe pas
    # TODO: tester si le marker de fin n'existe pas
    # TODO: tester si le marker de début n'existe pas
    result = (content.split(start_marker))[1].split(end_marker)[0]
    result = "\n".join([start_marker, result, end_marker])

    return result


def get_jahia_redirections(content):
    """
    Return jahia redirections from htaccess file
     
    :param content: content of htaccess file 
    :return: jahia redirections
    """
    jahia_page_redirect = extract_htaccess_part(content, "Jahia-Page-Redirect")
    jahia_files_redirect = extract_htaccess_part(content, "Jahia-Files-Redirect")
    jahia_redirect = "\n".join([jahia_page_redirect, jahia_files_redirect])
    logging.debug("Jahia redirections:\n{}".format(jahia_redirect))

    return jahia_redirect


def _copy_jahia_redirections(source_site_url, destination_site_url):

    # retrieve the content of the htaccess file from the source site
    source_site = SshRemoteSite(source_site_url)
    source_site_content = source_site.get_htaccess_content()

    # extract jahia rules
    jahia_redirections_content = get_jahia_redirections(source_site_content)

    # retrieve the content of the htaccess file from the destination site
    destination_site = SshRemoteSite(destination_site_url)
    destination_site_content = destination_site.get_htaccess_content()

    # insert jahia rules
    new_content = "\n".join([jahia_redirections_content, destination_site_content])

    destination_site.write_htaccess_content(new_content)


@dispatch.on('copy-jahia-redirections')
def copy_jahia_redirections(source_site_url, destination_site_url, **kwargs):

    logging.info("Starting copy jahia redirections from {} to {} ".format(source_site_url, destination_site_url))
    _copy_jahia_redirections(source_site_url, destination_site_url)
    logging.info("End of copy jahia redirections from {} to {} ".format(source_site_url, destination_site_url))


@dispatch.on('copy-jahia-redirections-many')
def copy_jahia_redirections_many(csv_file, **kwargs):

    rows = Utils.csv_filepath_to_dict(csv_file)

    logging.info("Starting copy jahia redirections for {} sites".format(len(rows)))
    for index, row in enumerate(rows):

        source_site_url = row['source_site_url']
        destination_site_url = row['destination_site_url']

        logging.info("Starting copy jahia redirections from {} to {} ".format(source_site_url, destination_site_url))
        _copy_jahia_redirections(source_site_url, destination_site_url)
        logging.info("End of copy jahia redirections from {} to {} ".format(source_site_url, destination_site_url))


@dispatch.on('update-redirections')
def update_redirections(site_url, **kwargs):
    logging.info("Starting update redirections from {} ".format(site_url))

    site = SshRemoteSite(site_url)

    new_content = "# BEGIN {}".format(WP_REDIRECTS_AFTER_VENTILATION),
    new_content += "RewriteRule ^(.*)$ {}$1 [L,QSA,R=301]".format(site_url),
    new_content += "# END {}".format(WP_REDIRECTS_AFTER_VENTILATION)

    site.write_htaccess_content(new_content)

    logging.info("End of update redirections from {} ".format(site_url))


@dispatch.on('update-redirections-many')
def update_redirections_many(site_url, **kwargs):
    pass


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
