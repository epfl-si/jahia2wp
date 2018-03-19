"""All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
jahia2wp: an amazing tool !

Usage:
  jahia2wp.py download              <site>                          [--debug | --quiet]
    [--username=<USERNAME> --host=<HOST> --zip-path=<ZIP_PATH> --force]
  jahia2wp.py download-many         <csv_file>                      [--debug | --quiet]
    [--output-dir=<OUTPUT_DIR>]
  jahia2wp.py unzip                 <site>                          [--debug | --quiet]
    [--username=<USERNAME> --host=<HOST> --zip-path=<ZIP_PATH> --force]
    [--output-dir=<OUTPUT_DIR>]
  jahia2wp.py parse                 <site>                          [--debug | --quiet]
    [--output-dir=<OUTPUT_DIR>] [--use-cache]
  jahia2wp.py export     <site>  <wp_site_url> <unit_name>          [--debug | --quiet]
    [--to-wordpress | --clean-wordpress]
    [--admin-password=<PASSWORD>]
    [--output-dir=<OUTPUT_DIR>]
    [--installs-locked=<BOOLEAN> --updates-automatic=<BOOLEAN>]
    [--openshift-env=<OPENSHIFT_ENV> --theme=<THEME>]
    [--use-cache]
  jahia2wp.py clean                 <wp_env> <wp_url>               [--debug | --quiet]
    [--stop-on-errors]
  jahia2wp.py clean-many            <csv_file>                      [--debug | --quiet]
  jahia2wp.py check                 <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py generate              <wp_env> <wp_url>               [--debug | --quiet]
    [--wp-title=<WP_TITLE> --wp-tagline=<WP_TAGLINE> --admin-password=<PASSWORD>]
    [--theme=<THEME> --theme-faculty=<THEME-FACULTY>]
    [--installs-locked=<BOOLEAN> --automatic-updates=<BOOLEAN>]
    [--extra-config=<YAML_FILE>]
  jahia2wp.py backup                <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py version               <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py admins                <wp_env> <wp_url>               [--debug | --quiet]
  jahia2wp.py generate-many         <csv_file>                      [--debug | --quiet]
  jahia2wp.py export-many           <csv_file>                      [--debug | --quiet]
    [--output-dir=<OUTPUT_DIR> --admin-password=<PASSWORD>] [--use-cache]
  jahia2wp.py backup-many           <csv_file>                      [--debug | --quiet]
  jahia2wp.py rotate-backup         <csv_file>          [--dry-run] [--debug | --quiet]
  jahia2wp.py veritas               <csv_file>                      [--debug | --quiet]
  jahia2wp.py inventory             <path>                          [--debug | --quiet]
  jahia2wp.py extract-plugin-config <wp_env> <wp_url> <output_file> [--debug | --quiet]
  jahia2wp.py list-plugins          <wp_env> <wp_url>               [--debug | --quiet]
    [--config [--plugin=<PLUGIN_NAME>]] [--extra-config=<YAML_FILE>]
  jahia2wp.py update-plugins        <wp_env> <wp_url>               [--debug | --quiet]
    [--force] [--plugin=<PLUGIN_NAME>]
  jahia2wp.py update-plugins-many   <csv_file>                      [--debug | --quiet]
    [--force] [--plugin=<PLUGIN_NAME>]
  jahia2wp.py global-report <csv_file> [--output-dir=<OUTPUT_DIR>] [--use-cache] [--debug | --quiet]
  jahia2wp.py ventilate-urls <csv_file> <wp_env> [--fix-csv]                 [--debug | --quiet]

Options:
  -h --help                 Show this screen.
  -v --version              Show version.
  --debug                   Set log level to DEBUG [default: INFO]
  --quiet                   Set log level to WARNING [default: INFO]
"""
import getpass
import logging
import pickle
import subprocess

from datetime import datetime
import json

import csv
import os
import yaml
from collections import OrderedDict
from docopt import docopt
from docopt_dispatch import dispatch
from epflldap.ldap_search import get_unit_id
from rotate_backups import RotateBackups

import settings
from crawler import JahiaCrawler
from exporter.wp_exporter import WPExporter
from parser.jahia_site import Site
from settings import VERSION, FULL_BACKUP_RETENTION_THEME, INCREMENTAL_BACKUP_RETENTION_THEME, \
    DEFAULT_THEME_NAME, DEFAULT_CONFIG_INSTALLS_LOCKED, DEFAULT_CONFIG_UPDATES_AUTOMATIC
from tracer.tracer import Tracer
from unzipper.unzip import unzip_one
from utils import Utils
from veritas.casters import cast_boolean
from veritas.veritas import VeritasValidor
from wordpress import WPSite, WPConfig, WPGenerator, WPBackup, WPPluginConfigExtractor


def _check_site(wp_env, wp_url, **kwargs):
    """ Helper function to validate wp site given arguments """
    wp_site = WPSite(wp_env, wp_url, wp_site_title=kwargs.get('wp_title'))
    wp_config = WPConfig(wp_site)
    if not wp_config.is_installed:
        raise SystemExit("No files found for {}".format(wp_site.url))
    if not wp_config.is_config_valid:
        raise SystemExit("Configuration not valid for {}".format(wp_site.url))
    return wp_config


def _check_csv(csv_file):
    """
    Check validity of CSV file containing sites information

    Arguments keywords
    csv_file -- Path to CSV file

    Return
    Instance of VeritasValidator
    """
    validator = VeritasValidor(csv_file)

    # If errors found during validation
    if not validator.validate():
        for error in validator.errors:
            logging.error(error.message)
        raise SystemExit("Invalid CSV file!")

    return validator


def _get_default_language(languages):
    """
    Return the default language

    If the site is in multiple languages, English is the default language
    """
    if "en" in languages:
        return "en"
    else:
        return languages[0]


def _set_default_language_in_first_position(default_language, languages):
    """
    Set the default language in first position.
    It is important for the Polylang plugin that the default language is
    in first position.

    :param default_language: the default language
    :param languages: the list of languages
    """
    if len(languages) > 1:
        languages.remove(default_language)
        languages.insert(0, default_language)
    return languages


def _fix_menu_location(wp_generator, languages, default_language):
    """
    Fix menu location for Polylang. After import, menus aren't displayed correctly so we need to add polylang
    config to fix this.

    :param wp_generator: WPGenerator instance used to create website.
    """
    # Recovering installed theme
    theme = wp_generator.run_wp_cli("theme list --status=active --field=name --format=csv")
    if not theme:
        raise Exception("Cannot retrieve current active theme")

    nav_menus = {theme: {}}
    # Getting menu locations
    locations = wp_generator.run_wp_cli("menu location list --format=json")
    if not locations:
        raise Exception("Cannot retrieve menu location list")

    # Getting menu list
    menu_list = wp_generator.run_wp_cli("menu list --fields=slug,locations,term_id --format=json")
    if not menu_list:
        raise Exception("Cannot get menu list")

    # Looping through menu locations
    for location in json.loads(locations):

        # To store menu's IDs for all language and current location
        menu_lang_to_id = {}

        base_menu_slug = None
        # We have location, we have to found base slug of the menus which are at this location
        for menu in json.loads(menu_list):

            if location['location'] in menu['locations']:
                base_menu_slug = menu['slug']
                break

        # If location doesn't contain any menu, we skip it
        if base_menu_slug is None:
            continue

        # We now have location (loc) and menu base slug (slug)

        # Looping through languages
        for language in languages:

            # Defining current slug name depending on language
            if language == default_language:
                menu_slug = base_menu_slug
            else:
                menu_slug = "{}-{}".format(base_menu_slug, language)

            # Value if not found
            menu_lang_to_id[language] = 0
            # Looking for menu ID for given slug
            for menu in json.loads(menu_list):
                if menu_slug == menu['slug']:
                    menu_lang_to_id[language] = menu['term_id']
                    break

        # We now have information for all menus in all languages for this location so we add infos
        nav_menus[theme][location['location']] = menu_lang_to_id

    # We update polylang config
    if not wp_generator.run_wp_cli("pll option update nav_menus '{}'".format(json.dumps(nav_menus))):
        raise Exception("Cannot update polylang option")


def _add_extra_config(extra_config_file, current_config):
    """ Adds extra configuration information to current config

    Arguments keywords:
    extra_config_file -- YAML file in which is extra config
    current_config -- dict with current configuration

    Return:
    current_config dict merge with YAML file content"""
    if not os.path.exists(extra_config_file):
        raise Exception("Extra config file not found: {}".format(extra_config_file))

    extra_config = yaml.load(open(extra_config_file, 'r'))

    return {**current_config, **extra_config}


def _generate_csv_line(wp_generator):
    """
    Generate a CSV line to add to source of truth. The line contains information about exported WP site.

    :param wp_generator: Object used to create WP website
    :return:
    """
    # CSV columns in correct order for source of truth line generation
    csv_columns = OrderedDict()

    # Recovering values from WPGenerator or hardcode some
    csv_columns['wp_site_url'] = wp_generator._site_params['wp_site_url']  # from csv
    csv_columns['wp_tagline'] = wp_generator._site_params['wp_tagline']  # from parser
    csv_columns['wp_site_title'] = wp_generator._site_params['wp_site_title']  # from parser
    csv_columns['site_type'] = 'wordpress'
    csv_columns['openshift_env'] = 'subdomains'
    csv_columns['category'] = 'GeneralPublic'  # from csv
    csv_columns['theme'] = wp_generator._site_params['theme']  # from csv
    csv_columns['theme_faculty'] = wp_generator._site_params['theme_faculty']  # from parser
    csv_columns['status'] = 'yes'
    csv_columns['installs_locked'] = wp_generator._site_params['installs_locked']  # from csv (bool)
    csv_columns['updates_automatic'] = wp_generator._site_params['updates_automatic']  # from csv (bool)
    csv_columns['langs'] = wp_generator._site_params['langs']  # from parser
    csv_columns['unit_name'] = wp_generator._site_params['unit_name']  # from csv
    csv_columns['comment'] = 'Migrated from Jahia to WP'

    # Formatting values depending on their type/content
    for col in csv_columns:
        # Bool are translated to 'yes' or 'no'
        if isinstance(csv_columns[col], bool):
            csv_columns[col] = 'yes' if csv_columns[col] else 'no'
        # None become empty string
        elif csv_columns[col] is None:
            csv_columns[col] = ''

    logging.info("Here is the line with up-to-date information to add in source of truth:\n")
    logging.info('"%s"', '","'.join(csv_columns.values()))


@dispatch.on('download')
def download(site, username=None, host=None, zip_path=None, force=False, **kwargs):
    # prompt for password if username is provided
    password = None
    if username is not None:
        password = getpass.getpass(prompt="Jahia password for user '{}': ".format(username))
    crawler = JahiaCrawler(site, username=username, password=password, host=host, zip_path=zip_path, force=force)
    return crawler.download_site()


@dispatch.on('download-many')
def download_many(csv_file, output_dir=None, **kwargs):

    TRACER_FILE_NAME = "tracer_empty_jahia_zip.csv"

    if output_dir is None:
        output_dir = settings.JAHIA_ZIP_PATH

    tracer_path = os.path.join(output_dir, TRACER_FILE_NAME)

    rows = Utils.csv_filepath_to_dict(csv_file)

    # download jahia zip file for each row
    print("\nJahia  zip files will now be downloaded...")
    for index, row in enumerate(rows):
        print("\nIndex #{}:\n---".format(index))
        try:
            download(site=row['Jahia_zip'])
        except Exception:
            with open(tracer_path, 'a', newline='\n') as tracer:
                tracer.write(
                    "{}, {}\n".format(
                        '{0:%Y-%m-%d %H:%M:%S}'.format(datetime.now()),
                        row['Jahia_zip']
                    )
                )
                tracer.flush()
    logging.info("All jahia zip files downloaded !")


@dispatch.on('unzip')
def unzip(site, username=None, host=None, zip_path=None, force=False, output_dir=None, **kwargs):

    # get zip file
    zip_file = download(site, username, host, zip_path, force)

    if output_dir is None:
        output_dir = settings.JAHIA_DATA_PATH

    try:
        return unzip_one(output_dir, site, zip_file)

    except Exception as err:
        logging.error("%s - unzip - Could not unzip file - Exception: %s", site, err)
        raise err


@dispatch.on('parse')
def parse(site, output_dir=None, use_cache=False, **kwargs):
    """
    Parse the give site.
    """
    try:
        # create subdir in output_dir
        site_dir = unzip(site, output_dir=output_dir)

        # where to cache our parsing
        pickle_file = os.path.join(site_dir, 'parsed_%s.pkl' % site)

        # when using-cache: check if already parsed
        pickle_site = False
        if use_cache:
            if os.path.exists(pickle_file):
                with open(pickle_file, 'rb') as pickle_content:
                    pickle_site = pickle.load(pickle_content)
                    logging.info("Loaded parsed site from %s" % pickle_file)

        logging.info("Parsing Jahia xml files from %s...", site_dir)
        if pickle_site:
            site = pickle_site
        else:
            site = Site(site_dir, site)

        print(site.report)

        # always save the parsed data on disk, so we can use the
        # cache later if we want
        with open(pickle_file, 'wb') as output:
            logging.info("Parsed site saved into %s" % pickle_file)
            pickle.dump(site, output, pickle.HIGHEST_PROTOCOL)

        # log success
        logging.info("Site %s successfully parsed" % site)
        Tracer.write_row(site=site.name, step="parse", status="OK")

        return site

    except Exception as err:
        logging.error("%s - parse - Exception: %s", site, err)
        raise err


@dispatch.on('export')
def export(site, wp_site_url, unit_name, to_wordpress=False, clean_wordpress=False, admin_password=None,
           output_dir=None, theme=None, installs_locked=False, updates_automatic=False, openshift_env=None,
           use_cache=None, **kwargs):
    """
    Export the jahia content into a WordPress site.

    :param site: the name of the WordPress site
    :param wp_site_url: URL of WordPress site
    :param unit_name: unit name of the WordPress site
    :param to_wordpress: to migrate data
    :param clean_wordpress: to clean data
    :param admin_password: an admin password
    :param output_dir: directory where the jahia zip file will be unzipped
    :param theme: WordPress theme used for the WordPress site
    :param installs_locked: boolean
    :param updates_automatic: boolean
    :param openshift_env: openshift_env environment (prod, int, gcharmier ...)
    """

    # Download, Unzip the jahia zip and parse the xml data
    site = parse(site=site, use_cache=use_cache)

    # Define the default language
    default_language = _get_default_language(site.languages)

    # For polylang plugin, we need position default lang in first position
    languages = _set_default_language_in_first_position(default_language, site.languages)

    if not site.acronym[default_language]:
        logging.warning("No wp site title in %s", default_language)
        wp_site_title = None
    else:
        wp_site_title = site.acronym[default_language]

    if not site.theme[default_language] or site.theme[default_language] == "epfl":
        theme_faculty = ""
    else:
        theme_faculty = site.theme[default_language]

    if not site.title[default_language]:
        logging.warning("No wp tagline in %s", default_language)
        wp_tagline = None
    else:
        wp_tagline = site.title[default_language]

    info = {
        # information from parser
        'langs': ",".join(languages),
        'wp_site_title': wp_site_title,
        'wp_tagline': wp_tagline,
        'theme_faculty': theme_faculty,
        'unit_name': unit_name,

        # information from source of truth
        'openshift_env': openshift_env,
        'wp_site_url': wp_site_url,
        'theme': theme,
        'updates_automatic': updates_automatic,
        'installs_locked': installs_locked,

        # determined information
        'unit_id': get_unit_id(unit_name),
        'from_export': True
    }

    # Generate a WordPress site
    wp_generator = WPGenerator(info, admin_password)
    wp_generator.generate()

    wp_generator.install_basic_auth_plugin()

    if settings.ACTIVE_DUAL_AUTH:
        wp_generator.active_dual_auth()

    wp_exporter = WPExporter(
        site,
        wp_generator,
        output_dir
    )

    if to_wordpress:
        logging.info("Exporting %s to WordPress...", site.name)
        try:
            if wp_generator.get_number_of_pages() == 0:
                wp_exporter.import_all_data_to_wordpress()
                wp_exporter.write_redirections()
                _fix_menu_location(wp_generator, languages, default_language)
                logging.info("Site %s successfully exported to WordPress", site.name)
            else:
                logging.info("Site %s already exported to WordPress", site.name)
        except (Exception, subprocess.CalledProcessError) as e:
            Tracer.write_row(site=site.name, step=e, status="KO")
            if not settings.DEBUG:
                wp_generator.clean()
            raise e

        Tracer.write_row(site=site.name, step="export", status="OK")

    if clean_wordpress:
        logging.info("Cleaning WordPress for %s...", site.name)
        wp_exporter.delete_all_content()
        logging.info("Data of WordPress site %s successfully deleted", site.name)

    wp_generator.uninstall_basic_auth_plugin()
    wp_generator.enable_updates_automatic_if_allowed()

    _generate_csv_line(wp_generator)

    return wp_exporter


@dispatch.on('export-many')
def export_many(csv_file, output_dir=None, admin_password=None, use_cache=None, **kwargs):

    rows = Utils.csv_filepath_to_dict(csv_file)

    # create a new WP site for each row
    print("\n{} websites will now be generated...".format(len(rows)))
    for index, row in enumerate(rows):

        print("\nIndex #{}:\n---".format(index))
        # CSV file is utf-8 so we encode correctly the string to avoid errors during logging.debug display
        row_bytes = repr(row).encode('utf-8')
        logging.debug("%s - row %s: %s", row["wp_site_url"], index, row_bytes)

        try:
            export(
                site=row['Jahia_zip'],
                wp_site_url=row['wp_site_url'],
                unit_name=row['unit_name'],
                to_wordpress=True,
                clean_wordpress=False,
                output_dir=output_dir,
                theme=row['theme'],
                installs_locked=row['installs_locked'],
                updates_automatic=row['updates_automatic'],
                wp_env=row['openshift_env'],
                admin_password=admin_password,
                use_cache=use_cache
            )
        except (Exception, subprocess.CalledProcessError) as e:
            Tracer.write_row(site=row['Jahia_zip'], step=e, status="KO")


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


@dispatch.on('clean-many')
def clean_many(csv_file, **kwargs):

    rows = Utils.csv_filepath_to_dict(csv_file)

    # clean WP site for each row
    print("\n{} websites will now be cleaned...".format(len(rows)))
    for index, row in enumerate(rows):

        print("\nIndex #{}:\n---".format(index))
        # CSV file is utf-8 so we encode correctly the string to avoid errors during logging.debug display
        row_bytes = repr(row).encode('utf-8')
        logging.debug("%s - row %s: %s", row["wp_site_url"], index, row_bytes)

        clean(row['openshift_env'], row['wp_site_url'])


@dispatch.on('generate')
def generate(wp_env, wp_url,
             wp_title=None, wp_tagline=None, admin_password=None,
             theme=None, theme_faculty=None,
             installs_locked=None, updates_automatic=None,
             extra_config=None, **kwargs):
    """
    This command may need more params if reference to them are done in YAML file. In this case, you'll see an
    error explaining which params are needed and how they can be added to command line
    """

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
    all_params = {'openshift_env': wp_env,
                  'wp_site_url': wp_url,
                  'theme': theme or DEFAULT_THEME_NAME,
                  'installs_locked': installs_locked,
                  'updates_automatic': updates_automatic}

    # Adding parameters if given
    if theme_faculty is not None:
        all_params['theme_faculty'] = theme_faculty

    if wp_title is not None:
        all_params['wp_site_title'] = wp_title

    if wp_tagline is not None:
        all_params['wp_tagline'] = wp_tagline

    # if we have extra configuration to load,
    if extra_config is not None:
        all_params = _add_extra_config(extra_config, all_params)

    wp_generator = WPGenerator(all_params, admin_password=admin_password)

    if not wp_generator.generate():
        raise Exception("Generation failed. More info above")

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

    # CSV file validation
    validator = _check_csv(csv_file)

    # create a new WP site for each row
    print("\n{} websites will now be generated...".format(len(validator.rows)))
    for index, row in enumerate(validator.rows):
        print("\nIndex #{}:\n---".format(index))
        # CSV file is utf-8 so we encode correctly the string to avoid errors during logging.debug display
        row_bytes = repr(row).encode('utf-8')
        logging.debug("%s - row %s: %s", row["wp_site_url"], index, row_bytes)
        WPGenerator(row).generate()


@dispatch.on('backup-many')
def backup_many(csv_file, **kwargs):

    # CSV file validation
    validator = _check_csv(csv_file)

    # create a new WP site backup for each row
    print("\n{} websites will now be backuped...".format(len(validator.rows)))
    for index, row in enumerate(validator.rows):
        logging.debug("%s - row %s: %s", row["wp_site_url"], index, row)
        WPBackup(
            row["openshift_env"],
            row["wp_site_url"]
        ).backup()


@dispatch.on('rotate-backup')
def rotate_backup(csv_file, dry_run=False, **kwargs):

    # CSV file validation
    validator = _check_csv(csv_file)

    for index, row in enumerate(validator.rows):
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

    if not validator.validate():
        validator.print_errors()
    else:
        print("CSV file validated!")


@dispatch.on('extract-plugin-config')
def extract_plugin_config(wp_env, wp_url, output_file, **kwargs):

    ext = WPPluginConfigExtractor(wp_env, wp_url)

    ext.extract_config(output_file)


@dispatch.on('list-plugins')
def list_plugins(wp_env, wp_url, config=False, plugin=None, extra_config=None, **kwargs):
    """
    This command may need more params if reference to them are done in YAML file. In this case, you'll see an
    error explaining which params are needed and how they can be added to command line
    """

    # FIXME: When we will use 'unit_id' from CSV file, add parameter here OR dynamically get it from AD
    all_params = {'openshift_env': wp_env,
                  'wp_site_url': wp_url}

    # if we have extra configuration to load,
    if extra_config is not None:
        all_params = _add_extra_config(extra_config, all_params)

    print(WPGenerator(all_params).list_plugins(config, plugin))


@dispatch.on('update-plugins')
def update_plugins(wp_env, wp_url, plugin=None, force=False, **kwargs):

    _check_site(wp_env, wp_url, **kwargs)

    wp_generator = WPGenerator({'openshift_env': wp_env,
                                'wp_site_url': wp_url})

    wp_generator.update_plugins(only_one=plugin, force=force)

    print("Successfully updated WordPress plugin list at {}".format(wp_generator.wp_site.url))


@dispatch.on('update-plugins-many')
def update_plugins_many(csv_file, plugin=None, force=False, **kwargs):

    # CSV file validation
    validator = _check_csv(csv_file)

    # Update WP site plugins for each row
    print("\n{} websites will now be updated...".format(len(validator.rows)))
    for index, row in enumerate(validator.rows):
        print("\nIndex #{}:\n---".format(index))
        logging.debug("%s - row %s: %s", row["wp_site_url"], index, row)
        WPGenerator(row).update_plugins(only_one=plugin, force=force)


@dispatch.on('global-report')
def global_report(csv_file, output_dir=None, use_cache=False, **kwargs):

    "Generate a global report with stats like the number of pages, files and boxes"
    path = os.path.join(output_dir, "global-report.csv")

    logging.info("Generating global report at %s" % path)

    rows = Utils.csv_filepath_to_dict(csv_file)

    sites = []

    for index, row in enumerate(rows):
        try:
            sites.append(parse(site=row['Jahia_zip'], use_cache=use_cache))
        except Exception as e:
            logging.error("Site %s - Error %s", row['Jahia_zip'], e)

    # retrieve all the box types
    box_types = set()

    for site in sites:
        for key in site.num_boxes.keys():
            if key:
                box_types.add(key)

    # the base field names for the csv
    fieldnames = ["name", "pages", "files"]

    # add all the box types
    fieldnames.extend(sorted(box_types))

    # write the csv file
    with open(path, 'w') as csvfile:
        writer = csv.DictWriter(csvfile, fieldnames=fieldnames)

        # header
        writer.writeheader()

        # content
        for site in sites:
            writer.writerow(site.get_report_info(box_types))

@dispatch.on('ventilate-urls')
def url_mapping(csv_file, wp_env, fix_csv=False, **kwargs):
    """
    :param csv_file: CSV containing the URL mapping rules for source and destination.
    :param fix_csv: Try to fix the CSV when set to True.
    """
    
    pass
        
            
def _validate_mapping_csv(csv_file, fix_csv):
    """
    :param csv_file: CSV containing the URL mapping rules for source and destination.
    :param fix_csv: Try to fix the CSV when set to True.
    
    It validates the url mapping contained in the CSV file as source => destination. 
    The first line is treated as headers. 
    
    Refer to function :func:`url_mapping` for a full explanation.
    """
    
    return True

if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
