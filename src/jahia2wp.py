"""All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
jahia2wp: an amazing tool !

Usage:
  jahia2wp.py download              <site>                          [--debug | --quiet]
    [--username=<USERNAME> --host=<HOST> --zip-path=<ZIP_PATH> --force]
  jahia2wp.py clean                 <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py check                 <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py generate              <wp_env> <wp_url>               [--debug | --quiet]
    [--wp-title=<WP_TITLE> --admin-password=<ADMIN_PASSWORD>]
    [--owner=<OWNER_ID> --responsible=<RESPONSIBLE_ID>]
  jahia2wp.py backup                <wp_env> <wp_url>               [--debug | --quiet]
    [--backup-type=<BACKUP_TYPE>]
  jahia2wp.py version               <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py admins                <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py check-one             <wp_env> <wp_url>               [--debug | --quiet] [DEPRECATED]
  jahia2wp.py clean-one             <wp_env> <wp_url>               [--debug | --quiet] [DEPRECATED]
  jahia2wp.py generate-one          <wp_env> <wp_url>               [--debug | --quiet] [DEPRECATED]
    [--wp-title=<WP_TITLE> --admin-password=<ADMIN_PASSWORD>]
    [--owner=<OWNER_ID> --responsible=<RESPONSIBLE_ID>]
  jahia2wp.py generate-many         <csv_file>                      [--debug | --quiet]
  jahia2wp.py backup-many           <csv_file>                      [--debug | --quiet]
    [--backup-type=<BACKUP_TYPE>]
  jahia2wp.py veritas               <csv_file>                      [--debug | --quiet]
  jahia2wp.py inventory             <wp_env> <path>                 [--debug | --quiet]
  jahia2wp.py extract-plugin-config <wp_env> <wp_url> <output_file> [--debug | --quiet]
  jahia2wp.py list-plugins          <wp_env> <wp_url>               [--debug | --quiet]
    [--config] [--plugin=<PLUGIN_NAME>]

Options:
  -h --help                 Show this screen.
  -v --version              Show version.
  --debug                   Set log level to DEBUG (default is INFO)
  --quiet                   Set log level to WARNING (default is INFO)
"""
import logging
import getpass

from docopt import docopt
from docopt_dispatch import dispatch

from veritas.veritas import VeritasValidor
from wordpress import WPSite, WPConfig, WPGenerator, WPBackup, WPPluginConfigExtractor
from crawler import JahiaCrawler

from settings import VERSION
from utils import Utils, deprecated


@dispatch.on('download')
def download(site, username=None, host=None, zip_path=None, force=False, **kwargs):
    # prompt for password if username is provided
    password = None
    if username is not None:
        password = getpass.getpass(prompt="Jahia password for user '{}': ".format(username))
    crawler = JahiaCrawler(site, username=username, password=password, host=host, zip_path=zip_path, force=force)
    crawler.download_site()


def _check_site(wp_env, wp_url, **kwargs):
    """ Helper function to validate wp site given arguments """
    wp_site = WPSite(wp_env, wp_url, wp_default_site_title=kwargs.get('wp_title'))
    wp_config = WPConfig(wp_site)
    if not wp_config.is_installed:
        raise SystemExit("No files found for {}".format(wp_site.url))
    if not wp_config.is_config_valid:
        raise SystemExit("Configuration not valid for {}".format(wp_site.url))
    return wp_config


@dispatch.on('check-one')
@deprecated("Use 'check' instead")
def check_one(wp_env, wp_url, **kwargs):
    return check(wp_env, wp_url, **kwargs)


@dispatch.on('check')
def check(wp_env, wp_url, **kwargs):
    wp_config = _check_site(wp_env, wp_url, **kwargs)
    # run a few more tests
    if not wp_config.is_install_valid:
        raise SystemExit("Could not login or use site at {}".format(wp_config.wp_site.url))
    # success case
    print("WordPress site valid and accessible at {}".format(wp_config.wp_site.url))


@dispatch.on('clean-one')
@deprecated("Use 'clean' instead")
def clean_one(wp_env, wp_url, **kwargs):
    return clean(wp_env, wp_url, **kwargs)


@dispatch.on('clean')
def clean(wp_env, wp_url, **kwargs):
    _check_site(wp_env, wp_url, **kwargs)
    # config found: proceed with cleaning
    wp_generator = WPGenerator(wp_env, wp_url)
    if wp_generator.clean():
        print("Successfully cleaned WordPress site {}".format(wp_generator.wp_site.url))


@dispatch.on('generate-one')
@deprecated("Use 'generate' instead")
def generate_one(wp_env, wp_url, wp_title=None, admin_password=None, owner_id=None, responsible_id=None, **kwargs):
    return generate(
        wp_env, wp_url, wp_title=wp_title, admin_password=admin_password,
        owner_id=owner_id, responsible_id=responsible_id, **kwargs)


@dispatch.on('generate')
def generate(wp_env, wp_url, wp_title=None, admin_password=None, owner_id=None, responsible_id=None, **kwargs):
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


@dispatch.on('backup')
def backup(wp_env, wp_url, backup_type=None, **kwargs):
    wp_backup = WPBackup(wp_env, wp_url, backup_type=backup_type)
    if not wp_backup.backup():
        raise SystemExit("Backup failed. More info above")

    print("Successfully backed-up WordPress site for {}".format(wp_backup.wp_site.url))


@dispatch.on('version')
def version(wp_env, wp_url, **kwargs):
    wp_config = _check_site(wp_env, wp_url, **kwargs)
    # success case
    print(wp_config.wp_version)


@dispatch.on('admins')
def admins(wp_env, wp_url, **kwargs):
    wp_config = _check_site(wp_env, wp_url, **kwargs)
    # success case
    for admin in wp_config.admins:
        print(admin)


@dispatch.on('generate-many')
def generate_many(csv_file, **kwargs):
    # use Veritas to get valid rows
    rows = VeritasValidor.filter_valid_rows(csv_file)

    # create a new WP site for each row
    print("\n{} websites will now be generated...".format(len(rows)))
    for index, row in rows:
        print("\nIndex #{}:\n---".format(index))
        logging.debug("%s - row %s: %s", row["wp_site_url"], index, row)
        WPGenerator(
            row["openshift_env"],
            row["wp_site_url"],
            wp_default_site_title=row["wp_default_site_title"],
            owner_id=row["owner_id"],
            responsible_id=row["responsible_id"],
        ).generate()


@dispatch.on('backup-many')
def backup_many(csv_file, backup_type=None, **kwargs):
    # use Veritas to get valid rows
    rows = VeritasValidor.filter_valid_rows(csv_file)

    # create a new WP site backup for each row
    print("\n{} websites will now be backuped...".format(len(rows)))
    for index, row in rows:
        logging.debug("%s - row %s: %s", row["wp_site_url"], index, row)
        WPBackup(
            openshift_env=row["openshift_env"],
            wp_site_url=row["wp_site_url"],
            wp_default_site_title=row["wp_default_site_title"],
            backup_type=backup_type
        ).backup()


@dispatch.on('inventory')
def inventory(wp_env, path, **kwargs):
    logging.info("Building inventory...")
    print(";".join(['path', 'valid', 'url', 'version', 'db_name', 'db_user', 'admins']))
    for site_details in WPConfig.inventory(wp_env, path):
        print(";".join([
            site_details.path,
            site_details.valid,
            site_details.url,
            site_details.version,
            site_details.db_name,
            site_details.db_user,
            site_details.admins
        ]))
    logging.info("Inventory made for %s", path)


@dispatch.on('veritas')
def veritas(csv_file, **kwargs):
    validator = VeritasValidor(csv_file)

    validator.validate()

    validator.print_errors()


@dispatch.on('extract-plugin-config')
def extract_plugin_config(wp_env, wp_url, output_file, **kwargs):

    ext = WPPluginConfigExtractor(wp_env, wp_url)

    ext.extract_config(output_file)


@dispatch.on('list-plugins')
def list_plugins(wp_env, wp_url, config=False, plugin=None, **kwargs):

    print(WPGenerator(
            wp_env,
            wp_url).list_plugins(config, plugin))


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
