"""All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
manage_redirections: an amazing tool !

Usage:
  manage_redirections.py copy-jahia-redirections <source_site_url> <destination_site_url> [--debug | --quiet]
  manage_redirections.py copy-jahia-redirections-many <csv_file> [--debug | --quiet]
  manage_redirections.py update-redirections <source_site_url> <destination_site_url> [--debug | --quiet]
  manage_redirections.py update-redirections-many <csv_file> [--debug | --quiet]
  manage_redirections.py archive-wp-site <source_site_url> [--debug | --quiet]
  manage_redirections.py create-directory <csv_file> [--debug | --quiet]

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
from ops import SshRemoteSite, SshRemoteHost
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


def update_uploads_path(jahia_files_redirect, source_site, destination_site):
    """
    1. Extract directory uploads path like wp-content/uploads/2018/<month>/
    2. Select name of the first directory uploads file from source_site
    3. Search this file into destination_site and return the upload directory path
    4. Update htaccess content with the 'right' path
    """

    # Extract directory uploads path like wp-content/uploads/2018/<month>/
    jahia_redirect_list = jahia_files_redirect.split(" ")
    remote_uploads_dir_path = ""
    for element in jahia_redirect_list:
        if element.startswith("wp-content/uploads/"):
            remote_uploads_dir_path = element
            break

    # Select name of the first directory uploads file from source_site
    remote_jahia_uploads_dir_path = os.path.join(source_site.get_root_dir_path(), remote_uploads_dir_path[:-2])
    first_uploads_file = source_site.get_first_file_name(remote_jahia_uploads_dir_path)

    # Search this file into destination_site and return the upload directory path
    directory_path = destination_site.get_directory_path_contains(first_uploads_file)
    if directory_path:
        month = directory_path.split("/")[-2]

        # Update htaccess content with the 'right' path
        jahia_files_redirect = jahia_files_redirect.replace(
            remote_uploads_dir_path,
            'wp-content/uploads/2018/' + month + '/$2'
        )
    else:
        destination_uploads_dir = os.path.join(destination_site.get_root_dir_path(), 'wp-content/uploads/2018')
        logging.debug("The file {} was not found in the site {}".format(first_uploads_file, destination_uploads_dir))

    return jahia_files_redirect


def get_jahia_redirections(content, source_site, destination_site, destination_site_url):
    """
    Extract and return jahia redirections from htaccess content

    :param content: htaccess content (string)
    :return: jahia redirections
    """
    slug_full = ""
    jahia_page_redirect = extract_htaccess_part(content, "Jahia-Page-Redirect")
    lines = jahia_page_redirect.split("\n")
    jahia_page_redirect = ""
    for line in lines:
        if line.startswith("Redirect 301"):

            slug_full = ""
            if (destination_site_url.startswith("https://www.epfl.ch")):
                slug_full = destination_site_url.replace("https://www.epfl.ch", "")
            elif (destination_site_url.startswith("https://migration-wp.epfl.ch")):
                slug_full = destination_site_url.replace("https://migration-wp.epfl.ch", "")
            else:
                logging.error("URL starts with a strange string !")

            slug_instance_wp = destination_site.wp_path
            if not slug_instance_wp.startswith("/"):
                slug_instance_wp = "/" + slug_instance_wp

            elements = line.split(" /")

            # gauche tout sans le www.epfl.ch/
            line = " ".join([elements[0], slug_full + "/" + elements[1]])

            # droite si / => tout sans www.epefl si autre chose chemin juska l'instance
            if elements[2] == "":
                line += " " + slug_full
            else:
                line += " " + slug_instance_wp + "/" + elements[2]

        jahia_page_redirect += line + "\n"

    jahia_files_redirect = extract_htaccess_part(content, "Jahia-Files-Redirect")
    if jahia_files_redirect:

        jahia_files_redirect = update_uploads_path(jahia_files_redirect, source_site, destination_site)

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
    source_site = SshRemoteSite(source_site_url)
    if not source_site.is_valid():
        logging.debug("WP {} is not valid".format(source_site_url))
        return

    destination_site = SshRemoteSite(destination_site_url, discover_site_path=True)
    if not source_site.is_valid():
        logging.debug("WP {} is not valid".format(source_site_url))
        return

    # retrieve the content of the htaccess file from the source site
    source_site_content = source_site.get_htaccess_content()

    if not source_site_content:
        logging.debug("htaccess file is empty for site {}".format(source_site_url))
        return

    # if source_site comes from test infra, we need to delete the site name inside all 301 jahia redirections
    if source_site_url.startswith("https://migration-wp.epfl.ch/"):

        # prepare the search and replace '/<site/' by '/'
        if not source_site.wp_path.startswith('/'):
            source_site.wp_path = '/' + source_site.wp_path

        # search and replace '/<site/' by '/' in htaccess content
        source_site_content = source_site_content.replace(source_site.wp_path, "/")
        logging.debug("Rename all 301 jahia redirections without then site name: {}".format(source_site_content))

    # extract jahia rules
    jahia_redirections_content = get_jahia_redirections(source_site_content, source_site, destination_site, destination_site_url)

    # retrieve the content of the htaccess file from the destination site
    destination_site_content = destination_site.get_htaccess_content()
    if not source_site_content:
        logging.debug("htaccess is empty for site {}".format(destination_site_url))
        return

    # insert jahia rules
    new_content = "\n".join([jahia_redirections_content, destination_site_content])
    destination_site.write_htaccess_content(new_content)


def _update_redirections(source_site_url, destination_site_url):
    """
    Update redirections.
    In other words, we replace the content of the htaccess file with a 302 rule like :
    RewriteRule ^(.*)$ https://dcsl.epfl.ch$1 [L,QSA,R=301]
    """

    source_site = SshRemoteSite(source_site_url)

    #if not source_site.is_valid():
    #    logging.debug("WP {} is not valid".format(source_site_url))
    #    return

    # Create a htaccess backup with name .htacces.bak.timestamp
    is_backup_created = source_site.create_htaccess_backup()

    if not destination_site_url.endswith("/"):
        destination_site_url = destination_site_url + "/"

    if is_backup_created:
        new_content = "# BEGIN {}\n".format(WP_REDIRECTS_AFTER_VENTILATION)
        new_content += "RewriteCond %{HTTP_HOST}" + " ^{}$ [NC]\n".format(source_site_url.replace("https://", ""))
        new_content += "RewriteRule ^(.*)$ " + destination_site_url + "\$1 [L,QSA,R=301]\n"
        new_content += "# END {}\n".format(WP_REDIRECTS_AFTER_VENTILATION)

        source_site.write_htaccess_content(new_content)


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
def update_redirections(source_site_url, destination_site_url, **kwargs):
    """
    Update redirections.
    In other words, we replace the content of the htaccess file with a 302 rule like :
    RewriteRule ^(.*)$ https://dcsl.epfl.ch$1 [L,QSA,R=301]
    """
    logging.info("Starting update redirections from {} ".format(source_site_url))
    _update_redirections(source_site_url, destination_site_url)
    logging.info("End of update redirections from {} ".format(source_site_url))


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

        source_site_url = row['source_site_url']
        destination_site_url = row['destination_site_url']

        # source_site_url = row['OLD URL']
        # destination_site_url = row['NEW URL']
        # system = row['systeme']

        logging.info("Updating redirections for site n°{} {}".format(index, source_site_url))

        # if row['systeme'] == 'jahia':

        _update_redirections(source_site_url, destination_site_url)

        logging.info("End update redirections for site n°{} {}".format(index, source_site_url))


@dispatch.on('archive-wp-site')
def archive_wp_site(source_site_url, **kwargs):
    """
    1. mv /srv/subdomains/dcsl.epfl.ch/htdocs /srv/sandox/archive-wp.epfl.ch/htdocs/dcsl
    2. mkdir /srv/subdomains/dcsl.epfl.ch/htdocs
    3. cp /srv/sandox/archive-wp.epfl.ch/htdocs/dcsl/.htaccess /srv/subdomains/dcsl.epfl.ch/htdocs/
    4. Modify .htaccess file of archive site
    5. Search and replace
    """
    source_site = SshRemoteSite(source_site_url)
    site_name = source_site.site_name
    archive_site_url = source_site.archive_wp_site()

    archive_site = SshRemoteSite(archive_site_url)
    htaccess_content = archive_site.get_htaccess_content()

    # Modify 2 lines to add site_name :
    # RewriteBase /dcsl/
    # RewriteRule . /dcsl/index.php [L]
    lines = htaccess_content.split("\n")
    new_content = ""
    for line in lines:
        if "RewriteBase" in line:
            line = line.replace("RewriteBase /", "RewriteBase /{}/".format(site_name))

        if "RewriteRule . /index.php" in line:
            line = line.replace("RewriteRule . /index.php", "RewriteRule . /{}/index.php".format(site_name))
        new_content += line + "\n"

    # TODO uncomment this line below
    # archive_site.write_htaccess_content(new_content)

    # Search and replace
    # Example: https://information-systems.epfl.ch => https://archive-wp.epfl.ch/information-systems
    remote_cmd = "wp search-replace '{}' '{}' --path={}".format(
        source_site_url, archive_site_url, archive_site.get_root_dir_path()
    )
    # TODO uncomment this line below
    # archive_site.wp_cli(remote_cmd)


def _run_ssh(remote_cmd, success_msg=""):
    ssh = SshRemoteHost.prod.run_ssh(remote_cmd)
    if ssh.returncode == 0:
        logging.debug(success_msg)
    elif ssh.returncode == 1:
        logging.warning(ssh.stderr)
    elif ssh.returncode != 1:
        logging.error(ssh.stderr)


@dispatch.on('create-directory')
def create_directory(csv_file, **kwargs):

    rows = Utils.csv_filepath_to_dict(csv_file)
    logging.info("Creating directory for {} sites".format(len(rows)))
    for index, row in enumerate(rows, start=1):
        if row['systeme'] == 'jahia':

            SshRemoteHost.prod = SshRemoteHost('prod', host='ssh-wwp.epfl.ch', port=32222)

            domain = row['OLD URL'].replace("https://", "")
            if domain.endswith("/"):
                domain = domain[:-1]

            path = "/srv/subdomains/{}".format(domain)
            remote_cmd = "mkdir {}".format(path)
            _run_ssh(remote_cmd, success_msg="mkdir {} success".format(path))

            path += "/htdocs"
            remote_cmd = "mkdir {}".format(path)
            _run_ssh(remote_cmd, success_msg="mkdir {} success".format(path))

            remote_cmd = "touch {}/.htaccess".format(path)
            _run_ssh(remote_cmd, success_msg="touch .htaccess success")


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
