"""All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
jahia2wp: an amazing tool !

Usage:
  jahia2wp.py download              <site>                          [--debug | --quiet]
    [--username=<USERNAME> --host=<HOST> --zip-path=<ZIP_PATH> --force]
  jahia2wp.py clean                 <wp_env> <wp_url>               [--debug | --quiet]
    [--stop-on-errors]
  jahia2wp.py check                 <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py generate              <wp_env> <wp_url>               [--debug | --quiet]
    [--wp-title=<WP_TITLE> --admin-password=<PASSWORD>] --unit-name=<NAME>
    [--unit-id=<ID>] [--theme=<THEME> --theme-faculty=<THEME-FACULTY>]
    [--installs-locked=<BOOLEAN> --automatic-updates=<BOOLEAN>]
  jahia2wp.py backup                <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py version               <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py admins                <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py generate-many         <csv_file>                      [--debug | --quiet]
  jahia2wp.py backup-many           <csv_file>                      [--debug | --quiet]
  jahia2wp.py rotate-backup         <csv_file>          [--dry-run] [--debug | --quiet]
  jahia2wp.py veritas               <csv_file>                      [--debug | --quiet]
  jahia2wp.py inventory             <path>                          [--debug | --quiet]
  jahia2wp.py extract-plugin-config <wp_env> <wp_url> <output_file> [--debug | --quiet]
  jahia2wp.py list-plugins          <wp_env> <wp_url>               [--debug | --quiet]
     --unit-name=<NAME> [--config] [--plugin=<PLUGIN_NAME>]

Options:
  -h --help                 Show this screen.
  -v --version              Show version.
  --debug                   Set log level to DEBUG [default: INFO]
  --quiet                   Set log level to WARNING [default: INFO]
"""
import logging
import getpass

from docopt import docopt
from docopt_dispatch import dispatch

from rotate_backups import RotateBackups

from veritas.veritas import VeritasValidor
from veritas.casters import cast_boolean
from wordpress import WPSite, WPConfig, WPGenerator, WPBackup, WPPluginConfigExtractor
from crawler import JahiaCrawler

from settings import VERSION, FULL_BACKUP_RETENTION_THEME, INCREMENTAL_BACKUP_RETENTION_THEME, \
    DEFAULT_THEME_NAME, DEFAULT_CONFIG_INSTALLS_LOCKED, DEFAULT_CONFIG_UPDATES_AUTOMATIC
from utils import Utils


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


@dispatch.on('check')
def check(wp_env, wp_url, **kwargs):
    wp_config = _check_site(wp_env, wp_url, **kwargs)
    # run a few more tests
    if not wp_config.is_install_valid:
        raise SystemExit("Could not login or use site at {}".format(wp_config.wp_site.url))
    # success case
    print("WordPress site valid and accessible at {}".format(wp_config.wp_site.url))


@dispatch.on('clean')
def clean(wp_env, wp_url, stop_on_errors=False, **kwargs):
    # when forced, do not check the status of the config -> just remove everything possible
    if stop_on_errors:
        _check_site(wp_env, wp_url, **kwargs)
    # config found: proceed with cleaning
    # FIXME: Il faut faire un clean qui n'a pas besoin de unit_name
    wp_generator = WPGenerator({'openshift_env': wp_env, 'wp_site_url': wp_url})
    if wp_generator.clean():
        print("Successfully cleaned WordPress site {}".format(wp_generator.wp_site.url))


@dispatch.on('generate')
def generate(wp_env, wp_url, unit_name, unit_id=None,
             wp_title=None, admin_password=None,
             theme=None, theme_faculty=None,
             installs_locked=None, updates_automatic=None,
             **kwargs):

    # if nothing is specified we want a locked install
    if installs_locked is None:
        installs_locked = DEFAULT_CONFIG_INSTALLS_LOCKED
    else:
        installs_locked = cast_boolean(installs_locked)

    # if nothing is specified we want automatic updates
    if updates_automatic is None:
        updates_automatic = DEFAULT_CONFIG_UPDATES_AUTOMATIC
    else:
        updates_automatic = cast_boolean(updates_automatic)

    # FIXME: When we will use 'unit_id' from CSV file, add parameter here OR dynamically get it from AD
    params = {'openshift_env': wp_env,
              'wp_site_url': wp_url,
              'unit_name': unit_name,
              'theme': theme or DEFAULT_THEME_NAME}
    if unit_id is not None:
        params['unit_id'] = unit_id

    wp_generator = WPGenerator(params, admin_password=admin_password
    )
    if not wp_generator.generate():
        raise SystemExit("Generation failed. More info above")

    print("Successfully created new WordPress site at {}".format(wp_generator.wp_site.url))


@dispatch.on('backup')
def backup(wp_env, wp_url, **kwargs):
    wp_backup = WPBackup(wp_env, wp_url)
    if not wp_backup.backup():
        raise SystemExit("Backup failed. More info above")

    print("Successfull {} backup for {}".format(
        wp_backup.backup_pattern, wp_backup.wp_site.url))


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
        WPGenerator(row).generate()


@dispatch.on('backup-many')
def backup_many(csv_file, **kwargs):
    # use Veritas to get valid rows
    rows = VeritasValidor.filter_valid_rows(csv_file)

    # create a new WP site backup for each row
    print("\n{} websites will now be backuped...".format(len(rows)))
    for index, row in rows:
        logging.debug("%s - row %s: %s", row["wp_site_url"], index, row)
        WPBackup(
            row["openshift_env"],
            row["wp_site_url"]
        ).backup()


@dispatch.on('rotate-backup')
def rotate_backup(csv_file, dry_run=False, **kwargs):
    # use Veritas to get valid rows
    rows = VeritasValidor.filter_valid_rows(csv_file)

    for index, row in rows:
        path = WPBackup(row["openshift_env"], row["wp_site_url"]).path
        # rotate full backups first
        for pattern in ["*full.sql", "*full.tar"]:
            RotateBackups(
                FULL_BACKUP_RETENTION_THEME,
                dry_run=dry_run,
                include_list=[pattern]
            ).rotate_backups(path)
        # rotate incremental backups
        for pattern in ["*.list", "*inc.sql", "*inc.tar"]:
            RotateBackups(
                INCREMENTAL_BACKUP_RETENTION_THEME,
                dry_run=dry_run,
                include_list=[pattern]
            ).rotate_backups(path)


@dispatch.on('inventory')
def inventory(path, **kwargs):
    logging.info("Building inventory...")
    print(";".join(['path', 'valid', 'url', 'version', 'db_name', 'db_user', 'admins']))
    for site_details in WPConfig.inventory(path):
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
def list_plugins(wp_env, wp_url, unit_name, config=False, plugin=None, **kwargs):

    # FIXME: When we will use 'unit_id' from CSV file, add parameter here OR dynamically get it from AD
    print(WPGenerator({'openshift_env': wp_env,
                       'wp_site_url': wp_url,
                       'unit_name': unit_name}).list_plugins(config, plugin))


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
