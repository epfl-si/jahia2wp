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
    [--to-wordpress | --clean-wordpress | --to-dictionary]
    [--admin-password=<PASSWORD>]
    [--output-dir=<OUTPUT_DIR>]
    [--installs-locked=<BOOLEAN> --updates-automatic=<BOOLEAN>]
    [--openshift-env=<OPENSHIFT_ENV> --theme=<THEME>]
    [--use-cache]
    [--keep-extracted-files]
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
    [--keep-extracted-files]
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
  jahia2wp.py migrate-urls <csv_file> <wp_env>                    [--debug | --quiet]
    --root_wp_dest=</srv/../epfl,/srv/../inside> [--context=<intra|inter|full>]

Options:
  -h --help                 Show this screen.
  -v --version              Show version.
  --debug                   Set log level to DEBUG [default: INFO]
  --quiet                   Set log level to WARNING [default: INFO]
"""
import csv
import getpass
import json
import logging
import pickle
import subprocess
from collections import OrderedDict
from datetime import datetime
from pprint import pprint

import os
import shutil
import yaml
from docopt import docopt
from docopt_dispatch import dispatch
from epflldap.ldap_search import get_unit_id
from rotate_backups import RotateBackups

import settings
from crawler import JahiaCrawler
from exporter.dict_exporter import DictExporter
from exporter.wp_exporter import WPExporter
from parser.jahia_site import Site
from settings import VERSION, FULL_BACKUP_RETENTION_THEME, INCREMENTAL_BACKUP_RETENTION_THEME, \
    DEFAULT_THEME_NAME, BANNER_THEME_NAME, DEFAULT_CONFIG_INSTALLS_LOCKED, DEFAULT_CONFIG_UPDATES_AUTOMATIC
from tracer.tracer import Tracer
from unzipper.unzip import unzip_one
from utils import Utils
from veritas.casters import cast_boolean
from veritas.veritas import VeritasValidor
from wordpress import WPSite, WPConfig, WPGenerator, WPBackup, WPPluginConfigExtractor
from time import time as tt
from urllib.parse import urlparse
import itertools
import re


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
        pickle_file_path = os.path.join(site_dir, 'parsed_%s.pkl' % site)

        # when using-cache: check if already parsed
        pickle_site = False
        if use_cache:
            if os.path.exists(pickle_file_path):
                with open(pickle_file_path, 'rb') as pickle_content:
                    pickle_site = pickle.load(pickle_content)
                    logging.info("Using the cached pickle file at %s" % pickle_file_path)

        logging.info("Parsing Jahia xml files from %s...", site_dir)
        if pickle_site:
            site = pickle_site
        else:
            logging.info("Cache not used, parsing the Site")
            site = Site(site_dir, site)

        print(site.report)

        # always save the parsed data on disk, so we can use the
        # cache later if we want
        with open(pickle_file_path, 'wb') as output:
            logging.info("Parsed site saved into %s" % pickle_file_path)
            pickle.dump(site, output, pickle.HIGHEST_PROTOCOL)

        # log success
        logging.info("Site %s successfully parsed" % site)
        Tracer.write_row(site=site.name, step="parse", status="OK")

        return site

    except Exception as err:
        logging.error("%s - parse - Exception: %s", site, err)
        raise err


@dispatch.on('export')
def export(site, wp_site_url, unit_name, to_wordpress=False, clean_wordpress=False, to_dictionary=False,
           admin_password=None, output_dir=None, theme=None, installs_locked=False, updates_automatic=False,
           openshift_env=None, use_cache=None, keep_extracted_files=False, **kwargs):
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
    :param keep_extracted_files: command to keep files extracted from jahia zip
    """

    # Download, Unzip the jahia zip and parse the xml data
    site = parse(site=site, use_cache=use_cache, output_dir=output_dir)

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

    if not theme:
        # Setting correct theme depending on parsing result
        theme = BANNER_THEME_NAME if default_language in site.banner else DEFAULT_THEME_NAME

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
        default_language,
        output_dir=output_dir
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

    if to_dictionary:
        data = DictExporter.generate_data(site)
        pprint(data, width=settings.LINE_LENGTH_ON_PPRINT)

    wp_generator.uninstall_basic_auth_plugin()
    wp_generator.enable_updates_automatic_if_allowed()

    _generate_csv_line(wp_generator)

    if not keep_extracted_files:
        # Delete extracted zip files
        # We take dirname because site.base_path is the path to the subfolder in the zip.
        # Example : path_to_extract/dcsl/dcsl
        # And we want to delete path_to_extract/dcsl
        base_zip_path = os.path.dirname(os.path.abspath(site.base_path))
        logging.debug("Removing zip extracted folder '%s'", base_zip_path)
        if os.path.exists(base_zip_path):
            shutil.rmtree(base_zip_path)

    return wp_exporter


@dispatch.on('export-many')
def export_many(csv_file, output_dir=None, admin_password=None, use_cache=None,
                keep_extracted_files=False, **kwargs):

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
                use_cache=use_cache,
                keep_extracted_files=keep_extracted_files
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

    print("Successful {} backup for {}".format(
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
    """Generate a global report with stats like the number of pages, files and boxes"""

    path = os.path.join(output_dir, "global-report.csv")

    logging.info("Generating global report at %s" % path)

    rows = Utils.csv_filepath_to_dict(csv_file)

    # the reports
    reports = []

    # all the box types
    box_types = set()

    for index, row in enumerate(rows):
        try:
            # parse the Site
            site = parse(site=row['Jahia_zip'], use_cache=use_cache)

            # add the report info
            reports.append(site.get_report_info())

            # add the site's box types
            for key in site.num_boxes.keys():
                if key:
                    box_types.add(key)
        except Exception as e:
                logging.error("Site %s - Error %s", row['Jahia_zip'], e)

    # the base field names for the csv
    fieldnames = ["name", "pages", "files"]

    # add all the box types
    fieldnames.extend(sorted(box_types))

    # complete the reports with 0 for box types unknown to a Site
    for box_type in box_types:
        for report in reports:
            if box_type not in report:
                report[box_type] = 0

            # some reports have an empty string
            if "" in report:
                del report[""]

    # write the csv file
    with open(path, 'w') as csvfile:
        writer = csv.DictWriter(csvfile, fieldnames=fieldnames)

        # header
        writer.writeheader()

        for report in reports:
            try:
                writer.writerow(report)

            except Exception as e:
                logging.error("Site %s - Error %s", report['name'], e)


@dispatch.on('migrate-urls')
def url_mapping(csv_file, wp_env, context='intra', root_wp_dest=None, use_inventory=False, htaccess=True, **kwargs):
    """
    :param csv_file: CSV containing the URL mapping rules for source and destination.
    :param context: intra, inter, full. Replace the occurrences at intra, inter or both.

    It takes the mapping rules in a CSV, with 2 columns each: source => destination,
    where both are URLs in WP instances. The first row of the CSV are treated as
    headers.

    It first validates the format of the CSV, where source can refer to a whole site,
    a path or a leaf (page). It can fix automatically the CSV,
    by removing or adding trailing slashes and peforming other checks. If the CSV is
    correct, the process will continue otherwise it will stop.
    The CSV will also be  sorted by rule specificity from specific to generic. The CSV
    is then split by site in an effort to treat them in parallel.

    For each site, all the post URLs are obtained from its WP instance in a python
    structure using WP-CLI.

    The URLs are then matched to the rules extracted for the site. The first match is
    taken and if no match, an alert is raised.

    Once an URL has been matched, its content (including pages and subpages if it's a
    site or path) are ready to be moved into the new location.

    The resulting post(s) to be moved are inserted by hierarchy to make sure the parent
    content is present before inserting sub-posts.

    When trying to run it in parallel, consider that the bottlneck will be the writes at
    the destination, since n sites will be migrated to a smaller number k destinations
    (likely 2 main: www.epfl.ch and inside.epfl.ch).
    """
    if not context:
        context = 'intra'

    logging.info('Parsing target WP instances (inventory)...')
    t = tt()
    # Obtain the list of the WP instances in the destination root folders (e.g. /srv/hmuriel/httpd/epfl)
    if not root_wp_dest:
        raise SystemExit("No target location to scan for WP instances")
    # Split the root to get the target sites
    dest_sites = {}
    for path in root_wp_dest.split(','):
        # The path must be absolute
        if not os.path.isabs(path):
            logging.warn(
                'The target site path is not absolute: {}. skipping...'.format(path))
        else:
            # Using inventory() method == SLOW.... test: 15s
            if use_inventory:
                for site in WPConfig.inventory(path):
                    if site.valid != 'ok':
                        logging.warn(
                            'Target site not valid: {}, skipping...'.format(site.path))
                    else:
                        dest_sites[site.url] = site.path
            else:
                # Scan for valid instances in the target path (FAST method),
                # test: 0.3s, test with wpcli check: 5.5s
                # Find wp-config.php in the paths
                dirs = Utils.run_command(
                    'find "' + path + '" -name "wp-config.php" -exec dirname "{}" \;')
                dirs = dirs.split('\n')
                # Create a WP Site per matching dir and get its URL
                # Doing it this way since WP site has some checks for the path and URL building.
                for path in dirs:
                    wp_site = WPSite.from_path(path)
                    if wp_site:
                        # wp_config = WPConfig(wp_site)
                        # if wp_config.is_config_valid:
                        dest_sites[wp_site.url] = path
    pprint(dest_sites)

    # Extract all the sites as site (key) => paths (value)
    logging.info('[{:.2f}s] Rule parsing...'.format(tt() - t))
    t = tt()
    rulesets = {}
    rows = Utils.csv_filepath_to_dict(csv_file)
    local_env = 'https://jahia2wp-httpd/{}'
    for idx, row in enumerate(rows):
        source = row['source']
        # Split the path and take the first arg as site
        # Consider special case of http(s):// and local sites
        # ATTENTION: At this stage, a site = domain name. Relative paths are not considered as sites.
        # this is true at least during the 'consolidation' phase wp => wp
        site_name = source.split('//').pop().split('/').pop(0)
        # There can be 3 type of rules identified
        # 1. Root path = sitename = full site
        # 2. Partial Path = Intermediate path with children
        # 3. Full path = a leaf / page with no children.
        # It's expected to find * in the rules but its absence has the same
        # meaning (i.e. apply the rule to all the sub-content under the path
        # Sytactically, only 2 cases will be detected: root and non-root path.
        rule_type = 'path'
        # Remove the trailing * from the paths, not needed in text only replacement.
        source = source.strip().strip('*')
        if 'http://' not in source and 'https://' not in source:
            rule_type = 'root' if source.strip('/') == site_name else 'path'
            # Local development case, append the host
            site = local_env.format(site_name)
            source = local_env.format(source)
        else:
            site = '{}//{}'.format(source.split('//').pop(0), site_name)
            rule_type = 'root' if source.split(
                '//').pop().strip('/') == site_name else 'path'
        dest = row['destination'].strip()
        # IMPORTANT: Add trailing slash, specially since now the source gets translated
        # into the new intermediate WP URL that always has a trailing slash.
        dest = dest.strip('/') + '/'
        if 'http://' not in dest and 'https://' not in dest:
            # Local development case, append the host
            dest = local_env.replace('http://', 'https://').format(dest)
        # Start with an empty ruleset
        if site not in rulesets:
            rulesets[site] = []
        # Append the URL to the site's list
        rulesets[site].append((source, dest, rule_type))
    logging.info("{} total sites found.".format(len(rulesets)))
    logging.debug(rulesets)
    pprint(rulesets)

    # Iterate over all the sites to map and dump a CSV with the pages and
    # another one for the media / attachments. This will *greatly simplify* the
    # reinsertion.
    # Create a copy of the keys in a list to avoid dict changing warnings.
    logging.info('[{:.2f}s] CSV dumping...'.format(tt()-t))
    t = tt()
    files = {}
    medias = {}
    # Keep a copy of the installation paths to use WP cli later
    site_paths = {}
    # Create a copy of the keys to allow changes on the keyset
    for site in list(rulesets.keys()):
        logging.info('Treating site {}'.format(site))
        # Load wp_config using existing functions, can't use _check_site since it exits
        # if the site does not exist or is invalid.
        wp_conf = WPConfig(WPSite(wp_env, site))
        if not wp_conf.is_installed or not wp_conf.is_config_valid:
            cmd = 'Site {} is not installed or wp_config is invalid, skipping...'
            logging.warn(cmd.format(site))
            # Remove the site and its ruleset, no cross-reference will be updated (no need)
            del rulesets[site]
            continue

        # Dump the site content in plain CSV format.
        logging.info("Dumping CSV for site {}".format(site))
        # All the fields to retrieve from the wp_post table
        fields = 'ID,post_title,post_name,post_parent,guid,url,post_status,post_content'
        # Only pages, all without paging. Sort them by post_parent ascendantly for ease of
        # reinsertion to insert first parent pages and avoiding parentless children.
        params = '--post_type=page --nopaging --order=asc --orderby=ID --fields={} --format=csv'
        cmd = 'wp post list ' + params + ' --path={} > {}'
        csv_f = site.split('/').pop() + '.csv'
        files[site] = csv_f
        cmd = cmd.format(fields, wp_conf.wp_site.path, csv_f)
        logging.debug(cmd)
        Utils.run_command(cmd, 'utf8')
        # Append site path at the end of the CSV as a comment, useful for later processing.
        Utils.run_command('echo "#{}" >> {}'.format(wp_conf.wp_site.path, csv_f))
        # Convert the GUIDs to relative URLs to avoid rule replacement by URL
        pages = Utils.csv_filepath_to_dict(csv_f)
        # Write back the CSV file
        with open(csv_f, 'w', encoding='utf8') as f:
            site_url = urlparse(site)
            host = site_url._replace(path='').geturl()
            writer = csv.DictWriter(f, fieldnames=pages[0].keys())
            writer.writeheader()
            # FIX: scan if the content has relative URLs not starting with a slash /
            # in other words, links that don't point to GUIDs (i.e. /site_name/post_name but only post_name)    
            regex = re.compile(r'href="([^\/]+)"')
            site_path = site_url.path
            for p in pages:
                # Make it into a rel URL
                p['guid'] = p['guid'].replace(host, '')
                # Fix the rel links without domain / path info.
                p['post_content'] = regex.sub(r'href="{}/\g<1>/"'.format(site_path), p['post_content'])
                writer.writerow(p)
        # Backup file
        shutil.copyfile(csv_f, csv_f + '.bak')
        # Dump media / attachments
        fields = 'ID,post_title,post_name,post_parent,post_status,guid'
        params = '--post_type=attachment --nopaging --order=asc --fields={} --format=csv'
        cmd = 'wp post list ' + params + ' --path={} > {}'
        csv_m = site.split('/').pop() + '_media.csv'
        medias[site] = csv_m
        cmd = cmd.format(fields, wp_conf.wp_site.path, csv_m)
        logging.debug(cmd)
        Utils.run_command(cmd, 'utf8')
        # Backup file
        shutil.copyfile(csv_m, csv_m + '.bak')
        # Add an entry to the site paths dict
        site_paths[site] = wp_conf.wp_site.path

    logging.info('[{:.2f}s] Starting rule expansion in diff. langs...'.format(tt()-t))
    t = tt()
    ############
    # IMPORTANT: Translate the source URL using the intermediate WP instance.
    ############
    # Use port 8443 (wp-mgmt does not have 443=>8443 redirection for httpd cont.)
    for site in rulesets.keys():
        # IMPORTANT: Before expanding any rules, restore the .htaccess to remove ventilation redirs.
        # Restore the .htaccess (always)
        with open(site_paths[site] + '/.htaccess', 'r+', encoding='utf8') as f:
            lines = f.readlines()
            f.seek(0, 0)
            f.truncate()
            begin = [i for i, l in enumerate(lines) if 'BEGIN ventilation-redirs' in l] or [len(lines)]
            end = [i for i, l in enumerate(lines) if 'END ventilation-redirs' in l] or [len(lines)]
            for i, l in enumerate(lines):
                if i < begin[0] or i > end[0]:
                    f.write(l)
        # Iterate over the individual rules for the site
        ext_ruleset = []
        # Expand the rules to cover additional languages for multilang websites.
        # By default, there is only 1 rule (in the csv) per URL independently of
        # the number of langs.
        lngs = Utils.run_command('wp pll languages --path=' + site_paths[site])
        lngs = [l[:2] for l in lngs.split("\n")]
        if len(lngs) > 1:
            logging.info('Multilang site {} for {}, decoupling rules...'.format(lngs, site))

        for (src, dst, type_rule) in rulesets[site]:
            _src = urlparse(src)
            _src = _src._replace(netloc=_src.netloc + ':8443').geturl()
            # GET only the HEADERS *of course* in silent mode and ignoring cert validity
            # WARNING:::: This first curl call will only get the .htaccess redirection (i.e. page GUID)
            # The second call (redir) will translate the GUID into the actual URL that doesn't have a port
            # info, therefore curl has to stop at this level to avoid port errors.
            out = Utils.run_command('curl -I -s -k {}'.format(_src))
            # Parse the Location header if present.
            loc = [l.split('Location: ').pop().strip() for l in out.split('\n') if 'Location:' in l]
            if not loc:
                logging.warning('cURL fail for URL location in WP instance for {}, removing rule'.format(src))
                continue
            else:
                src = loc.pop()
                # Continue only if the location has a 8443 port.
                # Special case: The root URL does only need one cURL.
                if ':8443' in src:
                    out = Utils.run_command('curl -I -s -k {}'.format(src))
                    # Parse the Location header if present.
                    loc = [l.split('Location: ').pop().strip() for l in out.split('\n') if 'Location:' in l]
                    if not loc:
                        logging.warning('cURL fail for URL location in WP instance for {}, removing rule'.format(src))
                        continue
                    else:
                        src = loc.pop()
            ext_ruleset.append((src, dst, type_rule))
            if len(lngs) > 1:
                pages = Utils.csv_filepath_to_dict(files[site])
                # Iterate the pages grouped by number of langs
                for pi in range(0, len(pages), len(lngs)):
                    page_set = pages[pi:pi + len(lngs)]
                    # Check if one of the URLs matches the src
                    matches = [p['url'] for p in page_set if p['url'] == src]
                    if matches:
                        # Remove previous single-lang rule
                        ext_ruleset.pop()
                        # Build the ruleset for all langs and extend the current site rules
                        langs_ruleset = [(p['url'], dst, type_rule) for p in page_set]
                        ext_ruleset.extend(langs_ruleset)
                        break
        # Replace the current ruleset with the extended version
        rulesets[site] = ext_ruleset

    # Sort the rules from most generic to specific or the reverse (-1).
    logging.info('[{:.2f}s] Rule sorting...'.format(tt()-t))
    t = tt()
    for site in rulesets:
        rulesets[site].sort(key=lambda rule: len(rule[0].split('/')) * -1)
    pprint(rulesets)

    # Write the .htaccess redirections for the new URL mapping
    if htaccess:
        logging.info('[{:.2f}s] Writing .htaccess file ...'.format(tt()-t))
        t = tt()
        for site in rulesets.keys():
            with open(site_paths[site] + '/.htaccess', 'r+', encoding='utf8') as f:
                lines = f.readlines()
                rw_base = [l for l in lines if 'RewriteBase ' in l].pop().split(' ').pop().strip()
                f.seek(0, 0)
                f.write('# BEGIN ventilation-redirs\n')
                f.write('RewriteEngine On\n')
                f.write('RewriteBase {}\n'.format(rw_base))
                for (src, dst, type_rule) in rulesets[site]:
                    path = urlparse(src).path[len(rw_base):]
                    f.write('RewriteRule ^{}(.*)$ {}$1 [R=302,L]\n'.format(path, dst))
                f.write('# END ventilation-redirs\n')
                f.write(''.join(lines))

    # At this point all the CSV files are generated and stored by sitename*
    # Iterate over the rules and start applying them first to the post URL

    # Some stats for different replacement tools:
    # time perl -i -pe's/dcsl/dcsl2/g' dcsl.json;                     # 0m0.007s
    # time sed -i 's/dcsl/dcsl2/g' dcsl.json;                         # 0m0.002s
    # time awk '{gsub("dcsl", "dcsl2")}1' dcsl.json > dcsl.tmp;       # 0m0.002s
    # time python3 regex.py dcsl.json;                                # 0m0.028s
    # regex.py:
    """
    import fileinput
    if __name__ == "__main__":
        with fileinput.input(inplace=1, backup='.bak') as f:
            for line in f:
                line = line.replace('dcsl','dcsl2')
                print(line, end='')
    """

    logging.info('[{:.2f}s] Starting rule execution to replace URLs...'.format(tt()-t))
    t = tt()
    stats = {}
    site_keys = rulesets.keys()
    for site in site_keys:
        if site not in stats:
            stats[site] = {}
        for site2 in site_keys:
            # Intersite replacements: This is important to make sure that external links coming from other sites point
            # to the right / new location. It will be a NxN check that will be time consuming. That's the reason why
            # it's separated in a different block to let factorise it and add extra options to run it or not.
            if context == 'intra' and site != site2:
                continue
            # Intrasite replacements: All the links inside the site will be replaced
            # according to the URL rules. There is no semantics yet like checking for
            # a ressource existence (e.g. images). A separate structure will be used
            # to map back images to port.
            if context == 'inter' and site == site2:
                continue

            # Target CSV file where to search matches to the rules of the current site.
            csv_f = files[site2]
            csv_m = medias[site2]
            # Ruleset for the site, IMPORTANT: the order has to be specific to generic
            ruleset = rulesets[site]
            if site2 not in stats[site]:
                stats[site][site2] = []
            for (source, dest, _) in ruleset:
                # Check both protocols, don't trust the source / content
                matches = {}
                for prot in ['https', 'http']:
                    _source = source
                    if prot + '://' not in _source:
                        _source = prot + '://' + _source.split('//').pop()
                    cmd = "awk 'END{{print t > \"/dev/stderr\"}}"
                    cmd_m = cmd = cmd + " {{t+=gsub(\"{0}\",\"{1}\")}}1' {2} > {2}.1 && mv {2}.1 {2}"
                    cmd = cmd.format(_source, dest, csv_f)
                    cmd_m = cmd_m.format(_source, dest, csv_m)
                    logging.debug(cmd, cmd_m)
                    # AWK is counting the replacement occurrences in the stderr.
                    proc = subprocess.run(
                        cmd, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                    subprocess.run(
                        cmd_m, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                    # This has to be a number normally, but it can also contain return errors, to be analized
                    reps = proc.stderr.strip()
                    matches[prot] = reps
                try:
                    tot_reps = sum([int(x) for x in matches.values()])
                except:
                    tot_reps = '; '.join(matches.values())
                stats[site][site2].append((tot_reps, source, dest, matches))
    pprint(stats)

    # Replace relative links per site using the GUID of each page as key. Recall, the GUID was converted
    # to a relative link for this purpose.
    dest_sites_keys = dest_sites.keys()
    stats = {}
    for site in site_keys:
        if site not in stats:
            stats[site] = []
        csv_f = files[site]
        pages = Utils.csv_filepath_to_dict(csv_f)
        # Locate the GUID column in the CSV header and find if it has colons before and after it.
        # This is useful to replace exactly that column, for the href="" attr, the quotes will be
        # used instead.
        with open(csv_f, 'r', encoding='utf8') as f:
            head = f.readline()
            bef_guid = ',' if head.find(',guid') != -1 else ''
            aft_guid = ',' if head.find('guid,') != -1 else ''
        for p in pages:
            guid = p['guid']
            # The postname is the last fragment of the GUID (i.e. relative URL, site/post_name)
            prev_post_name = urlparse(guid).path.strip('/').split('/')[-1]
            # Find the longest matching URL among the target sites
            matches = [s for s in dest_sites_keys if s in p['url']]
            if not matches:
                logging.warning('No matching destination site for page {}, skipping...'.format(guid))
            else:
                max_match = '/'.join(max([m.split('/') for m in matches]))
                logging.debug('{} to {}'.format(p['url'], max_match))
                # Keep the same post_name if it's the index page
                if prev_post_name == 'index-html':
                    # This has to be the same otherwise the redirection to the home (/) will not work.
                    post_name = prev_post_name
                else:
                    # Get the last fragment of the new / migrated URL, that's always the post_name
                    # (e.g. /epfl/research/dcsl/page-1334-en-html, page-1334-en-html is the post_name)
                    post_name = urlparse(p['url']).path.strip('/').split('/')[-1]

                # The URL path is necessary to build the right GUID at the destination (e.g. dirs/site_name/post_name)
                post_path = urlparse(max_match).path
                # Use the path and post_name to build the new GUID
                new_guid = os.path.join(post_path, post_name + '/')
                # Replace first the GUID column and then the href occurrences in the text
                cmd = "awk 'END{{print t > \"/dev/stderr\"}}"
                cmd_body = cmd = cmd + " {{t+=gsub(\"{0}\",\"{1}\")}}1' {2} > {2}.1 && mv {2}.1 {2}"
                # Replace the GUID column
                cmd = cmd.format(bef_guid + guid + aft_guid, bef_guid + new_guid + aft_guid, csv_f)
                # Replace the HTML attrs (e.g. href="") containing the GUID
                cmd_body = cmd_body.format(guid, new_guid, csv_f)
                logging.debug(cmd_body)
                # AWK is counting the replacement occurrences in the stderr.
                proc = subprocess.run(cmd, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                proc_body = subprocess.run(cmd_body, shell=True, stderr=subprocess.PIPE, universal_newlines=True)
                # This has to be a number normally, but it can also contain return errors, to be analized
                reps = proc_body.stderr.strip()
                stats[site].append((reps, guid, new_guid, site))
    logging.info('Replacing relative URLs (both relative GUID and post_name) finished:')
    pprint(stats)

    # At this point all the CSV files have the right URLs in place. It is the moment to effectively migrate
    # the content (pages and files / media)
    # ASSUMPTION: All the content is to migrate, there is no content to left behind (e.g. old pages). In any
    # case, such content can be removed later in the target destination.
    # The migration / insertion of the content is performed by site but it can be parallelised in lots of n
    # sites out of the N, the right value is to define while testing according to the available ressources.

    dest_sites_keys = dest_sites.keys()
    logging.info(
        '[{:.2f}s], Preparing insertion in target WP instances...'.format(tt()-t))
    t = tt()
    # Store the new keys per site after the insertion as URL => ID
    # This is useful to set the new parents
    table_ids = {}
    for site in site_keys:
        menu_siteurl = None
        logging.info('Treating site ' + site)
        # Source CSV files from where to take the content
        # Start with the pages since it'll be faster than the media
        csv_f = files[site]
        # Get the languages
        lngs = Utils.run_command('wp pll languages --path=' + site_paths[site])
        lngs = [l[:2] for l in lngs.split("\n")]
        # Get all the pre-replaced pages from the CSV
        pages = Utils.csv_filepath_to_dict(csv_f)
        # Group the pages by group of langs and sort them by URL components
        # The key concept is to avoid children being inserted before parents
        pages = [pages[pi:pi+len(lngs)] for pi in range(0, len(pages), len(lngs))]
        pages = sorted(pages, key=lambda p: len(p[0]['url'].strip('/').split('/')))
        # Unpack pages as a list again
        pages = list(itertools.chain.from_iterable(pages))

        # Get the content in all the languages per page
        # The IDs are sequential in source WP (e.g. 580 => en, 581=>fr).
        # Therefore group them by number of languages.
        # ATTENTION: check for safety and errors (.e.g missing lang for page?)
        table_ids[site] = {}
        for pi in range(0, len(pages), len(lngs)):
            # All pages
            _pages = pages[pi:pi+len(lngs)]

            # ATTENTION: Selecting the page in EN since all URLs will be rewritten
            # in english.
            p_en = _pages[lngs.index('en')]
            logging.info("[en] Page {} {}".format(p_en['post_name'], p_en['url']))
            # Find the longest matching URL among the target sites
            matches = [s for s in dest_sites_keys if s in p_en['url']]
            if not matches:
                logging.warning('No matching destination site for page, skipping...')
            else:
                max_match = '/'.join(max([m.split('/') for m in matches]))
                # print(max_match, p_en['url'])
                # Old IDs
                old_ids = [p['ID'] for p in _pages]
                # Update the parent ID if a new one exists already
                for _p in _pages:
                    if max_match in table_ids[site] and _p['post_parent'] in table_ids[site][max_match]:
                        _p['post_parent'] = table_ids[site][max_match][_p['post_parent']]
                    else:
                        # Set the page at the root: post_parent 0
                        _p['post_parent'] = 0
                # Rename the post if necessary to have a pretty (matching) URL
                # If there is only one fragment and it's different of the post_name
                # then change it.
                fragment = p_en['url'][len(max_match)+1:].strip('/')
                if len(fragment.split('/')) == 1:
                    if p_en['post_name'] != fragment and max_match != p_en['url'].strip('/'):
                        p_en['post_name'] = fragment
                        # for p in _pages:
                        #    p['post_name'] = fragment
                # JSON file to contain the post data
                tmp_json = ".tmp_{}_{}.json".format(site.split('/').pop(), _pages[0]['ID'])
                cmd = "cat {} | wp pll post create --post_type=page --porcelain --stdin --path={}"
                # Remove / Change attrs before dumping the post JSON.
                _pagesi = [{k: v for k, v in _p.items() if k not in ['ID', 'url']} for _p in _pages]
                arr = ['"{}":{}'.format(lngs[i], json.dumps(p, ensure_ascii=False)) for i, p in enumerate(_pagesi)]
                json_data = '{' + ', '.join(arr) + '}'
                # Dump the JSON to a file to avoid non escaped char issues.
                with open(tmp_json, 'w', encoding='utf8') as j:
                    j.write(json_data)
                cmd = cmd.format(tmp_json, dest_sites[max_match])
                logging.info(cmd)
                ids = Utils.run_command(cmd, 'utf8').split(' ')
                if 'Error' in ids:
                    logging.error('Failed to insert pages. Msg: {}. cmd: {}'.format(ids, cmd))
                else:
                    logging.info('new IDs {} in lang order {}'.format(ids, lngs))
                    # Keep the new IDs
                    if max_match not in table_ids[site]:
                        table_ids[site][max_match] = {}
                    for old_id, new_id in zip(old_ids, ids):
                        table_ids[site][max_match][old_id] = new_id
                    # VERIFY: Setting homepage instead of the default WP options
                    # Using URL in EN by default
                    if max_match == p_en['url'].strip('/'):
                        # Set the menu flag
                        menu_siteurl = max_match
                        logging.info('Updating home page for site {} to ID {}'.format(max_match, ids[0]))
                        cmd = 'wp option update show_on_front page --path={}'.format(dest_sites[max_match])
                        msg = Utils.run_command(cmd, 'utf8')
                        if 'Success' not in msg:
                            logging.warning('Could not set show_on_front option! Msg: {}. cmd: {}', msg, cmd)
                        cmd = 'wp option update page_on_front {} --path={}'.format(ids[0], dest_sites[max_match])
                        msg = Utils.run_command(cmd, 'utf8')
                        if 'Success' not in msg:
                            logging.warning('Could not set page_on_front option! Msg: {}. cmd: {}', msg, cmd)

        if menu_siteurl:
            path = site_paths[site]
            # Get the langs, the first one is the default
            lngs = Utils.run_command('wp pll languages --path=' + path)
            lngs = [l[:2] for l in lngs.split("\n")]
            # Getting menu list at the source (site)
            cmd = 'wp menu list --fields=slug,locations,term_id --format=json --path={}'.format(path)
            menu_list = Utils.run_command(cmd, 'utf8')
            logging.info('Current menu at source: {}'.format(menu_list))
            if not menu_list:
                logging.error("Cannot get menu list for {}".format(path))
                continue
            menu_list = json.loads(menu_list)
            # Construct [location]=>[language]=>[menu term_id]
            loc_lang_menu = {}
            curr_loc = ''
            for menu in menu_list:
                slug = menu['slug'].split('-')
                # The default language is always listed first
                lng = lngs[0]
                if len(slug) == 1:
                    curr_loc = menu['locations'][0]
                    loc_lang_menu[curr_loc] = {lng: menu['term_id']}
                else:
                    lng = slug[1]
                    loc_lang_menu[curr_loc][lng] = menu['term_id']
            # Start doing the mapping between the old page IDs and the new IDs at the destination.
            # Do not replace the DB_IDs yet, only after the menu items get inserted at the destination.
            menu_items = {}
            for menu in menu_list:
                fields = 'db_id,type,type_label,position,menu_item_parent,object_id,object,type_label'
                fields += ',link,title,target,attr_title,description,classes,xfn'
                cmd = 'wp menu item list {} --fields={} --format=json --path={}'.format(menu['term_id'], fields, path)
                items_json = Utils.run_command(cmd, 'utf8')
                items = json.loads(items_json)
                for i in range(len(items) - 1, -1, -1):
                    item = items[i]
                    if item['object'] == 'page':
                        pid = item['object_id']
                        if pid in table_ids[site][menu_siteurl]:
                            new_pid = table_ids[site][menu_siteurl][pid]
                            item['object_id'] = new_pid
                        else:
                            # Remove the menu item
                            logging.info('removing item {}, since no new ID at destination'.format(item['db_id']))
                            del items[i]
                menu_items[menu['term_id']] = items
                logging.debug("menu items at source for {}: {}".format(menu['slug'], items_json))

            # Get the langs at the destination, first one is the default, must be EN.
            dst_path = dest_sites[menu_siteurl]
            dst_lngs = Utils.run_command('wp pll languages --path=' + dst_path)
            dst_lngs = [l[:2] for l in dst_lngs.split("\n")]
            # Getting menu list at the destination site
            cmd = 'wp menu list --fields=slug,locations,term_id --format=json --path={}'.format(dst_path)
            dst_menu_list = Utils.run_command(cmd, 'utf8')
            if not dst_menu_list:
                logging.error("Cannot get menu list for {}".format(dst_path))
                continue
            logging.info('Setting menu for {} at {} '.format(menu_siteurl, dst_path))
            logging.info('Current menu at destination: {}'.format(dst_menu_list))
            # Construct [location]=>[language]=>[menu term_id]
            dst_loc_lang_menu = {}
            curr_loc = ''
            for menu in json.loads(dst_menu_list):
                slug = menu['slug'].split('-')
                # The default language is always listed first
                lng = dst_lngs[0]
                if len(slug) == 1:
                    locs = menu['locations']
                    if not locs:
                        # Imply the location
                        if menu['slug'] == 'main':
                            locs = ['top']
                        if menu['slug'] == 'footer_nav':
                            locs = ['footer_nav']
                    curr_loc = locs[0]
                    dst_loc_lang_menu[curr_loc] = {lng: menu['term_id']}
                else:
                    lng = slug[1]
                    dst_loc_lang_menu[curr_loc][lng] = menu['term_id']
            # print(loc_lang_menu, dst_loc_lang_menu)

            # All the menues are ready to be inserted at the destination.
            # They are ordered by position and parent items appear before. Therefore, the parent
            # menu (db_id) will be updated as they get inserted. This is important since WP automatically
            # creates new db_id entries.
            db_ids = {}
            for loc in loc_lang_menu:
                for lang in loc_lang_menu[loc]:
                    # Local term_id
                    menu_id = loc_lang_menu[loc][lang]
                    items = menu_items[menu_id]
                    # Destination menu term_id
                    if loc in dst_loc_lang_menu and lang in dst_loc_lang_menu[loc]:
                        dst_menu_id = dst_loc_lang_menu[loc][lang]
                        # Iterate over the individual items inserting them one at a time.
                        for it in items:
                            # Check if a new db_id is assigned to the parent
                            mpid = it['menu_item_parent']
                            if mpid != '0' and mpid in db_ids:
                                print('setting parent id to {} from {}'.format(db_ids[mpid], mpid))
                                it['menu_item_parent'] = db_ids[mpid]
                            fields = '--description={} --attr-title="{}" --target={} --classes="{}" --position={}'
                            fields += ' --parent-id={}'
                            attrs = [it['description'], it['attr_title'], it['target'], it['classes'], it['position']]
                            attrs += [it['menu_item_parent']]
                            if it['object'] == 'page':
                                fields += ' --title="{}"'
                                attrs += [it['title']]
                                fields = fields.format(*attrs)
                                cmd = 'wp menu item add-post {} {} {} --porcelain --path={}'
                                cmd = cmd.format(dst_menu_id, it['object_id'], fields, dst_path)
                            if it['object'] == 'custom':
                                fields = fields.format(*attrs)
                                cmd = 'wp menu item add-custom {} "{}" "{}" {} --porcelain --path={}'
                                cmd = cmd.format(dst_menu_id, it['title'], it['link'], fields, dst_path)
                            new_db_id = Utils.run_command(cmd, 'utf8')
                            # Fix: db_id is an int and menu_item_parent and new_db_id are str
                            db_id = str(it['db_id'])
                            db_ids[db_id] = new_db_id
                    else:
                        logging.error('No menu at destination for location {} and lang {}'.format(loc, lang))

            # Force polylang update to display the menu properly
            cmd = 'wp theme list --status=active --field=name --format=csv --path={}'.format(dst_path)
            theme = Utils.run_command(cmd, 'utf8')
            if not theme:
                logging.error('Cannot retrieve current active theme for {}'.format(dst_path))
                continue
            nav_menus = {theme: dst_loc_lang_menu}
            # Update polylang option
            cmd = "wp pll option update nav_menus '{}' --path={}".format(json.dumps(nav_menus), dst_path)
            print("nav_menu updated: " + json.dumps(nav_menus))
            logging.info(Utils.run_command(cmd, 'utf8'))
    logging.info(
        '[{:.2f}s], Preparing media insertion in target WP instances...'.format(tt()-t))
    t = tt()
    for site in site_keys:
        # The media are the last to be inserted since it will take longer.
        csv_m = medias[site]


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
