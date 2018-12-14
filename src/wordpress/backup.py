import datetime
import logging
import re
import os

from .models import WPException, WPSite
from .config import WPConfig

from django.core.validators import URLValidator
from veritas.validators import validate_openshift_env

from utils import Utils
import settings

from prometheus_client import CollectorRegistry, Gauge, push_to_gateway
from prometheus_client.exposition import basic_auth_handler


class WPBackup:
    """
    Class that handles the backups.

    There are 2 types of backup, that will be automatically made according the following :
    - full : Full backup, when no other full backup found in the NB_DAYS_BEFORE_NEW_FULL
    - inc : Incremental backup (for files only, not the DB), when full backup found (in NB_DAYS_BEFORE_NEW_FULL)

    A full backup generates 3 files :
    - "<BACKUP_PATH>/<wp_site.path>/<timestamp>.list": reference for incremental backup
    - "<BACKUP_PATH>/<wp_site.path>/<timestamp>_full.tar": to save of files
    - "<BACKUP_PATH>/<wp_site.path>/<timestamp>_full.sql": the db dump

    A incremental backup generates 2 files :
    - "<BACKUP_PATH>/<wp_site.path>/<timestamp>_inc.tar": to save files
    - "<BACKUP_PATH>/<wp_site.path>/<timestamp>_inc.sql": the db dump
    """

    FULL_PATTERN = "full"
    INCREMENTAL_PATTERN = "inc"
    TIMESTAMP_FORMAT = "%Y%m%d%H%M%S"
    # allow to override BACKUP_PATH in var env for travis (which uses a read-only system)
    BACKUP_PATH = Utils.get_optional_env("BACKUP_PATH", os.path.join(settings.DATA_PATH, 'backups'))

    def __init__(self, openshift_env, wp_site_url, full=False):
        """
        Class constructor

        Argument keywords:
        openshift_env -- Name of OpenShift environment on which script is executed
        wp_site_url -- URL to Website to backup
        """
        # validate input
        validate_openshift_env(openshift_env)
        if not wp_site_url.startswith('https://jahia2wp-httpd/'):
            URLValidator()(wp_site_url)

        # setup site and config
        self.wp_site = WPSite(openshift_env, wp_site_url)
        self.wp_config = WPConfig(self.wp_site)

        # set backup attributes
        self.datetime = datetime.datetime.now()
        self.timestamp = self.datetime.strftime(self.TIMESTAMP_FORMAT)
        self.path = os.path.join(self.BACKUP_PATH, self.wp_site.path.replace('/', '_'))
        # set backup type, and name for list file
        listfile = self.get_daily_list()
        if listfile is None or full:
            self.backup_pattern = self.FULL_PATTERN
            self.listfile = os.path.join(
                self.path,
                self.timestamp + ".list")
        else:
            self.backup_pattern = self.INCREMENTAL_PATTERN
            self.listfile = os.path.join(self.path, listfile)

        # set filenames
        self.tarfile = os.path.join(
                self.path,
                "_".join((self.timestamp, self.backup_pattern)) + ".tar")
        self.sqlfile = os.path.join(
                self.path,
                "_".join((self.timestamp, self.backup_pattern)) + ".sql")

    def get_daily_list(self):
        # shortcut if directory does not exist yet
        if not os.path.exists(self.path):
            return None

        # list all dates when we could use a full backup
        valid_dates = [self.datetime - datetime.timedelta(days=delta)
                       for delta in range(settings.NB_DAYS_BEFORE_NEW_FULL)]

        # build regex for filenames with found dates
        matches = "|".join([valid_date.strftime("%Y%m%d")
                            for valid_date in valid_dates])
        file_regex = re.compile("({})\d+.list".format(matches))

        # list directory, filtering out files with appropriate dates
        logging.debug("%s - Seeking backups with regex: %s", repr(self.wp_site), file_regex)
        matches = [name for name in os.listdir(self.path)
                   if file_regex.search(name)]

        # returns oldest match
        if matches:
            matches.sort()
            logging.info("%s - Existing full backup %s. Making incremental new one",
                         repr(self.wp_site), matches[0])
            return matches[0]

    def generate_wp_files(self):
        """
        Generate a tar file that contains all the files of the WordPress site

        raises WPException on failures
        """
        if not os.path.exists(self.wp_site.path):
            raise WPException("The WordPress site {} is not properly installed".format(repr(self.wp_site)))

        if not Utils.generate_tar_file(self.tarfile, self.listfile, self.wp_site.path):
            raise WPException("WP tar {} could not be created".format(self.tarfile))

        logging.debug("%s - WP tar %s is created", repr(self.wp_site), self.tarfile)

    def generate_db_dump(self):
        """
        Generate the database dump.

        raises WPException on failures
        """
        command = "db export {} --path={}".format(self.sqlfile, self.wp_site.path)
        if not self.wp_config.run_wp_cli(command):
            raise WPException("WP DB dump {} could not be created".format(self.sqlfile))

        logging.debug("%s - WP db dump %s is created", repr(self.wp_site), self.sqlfile)

    def backup(self):
        """
        Launch the backup
        """
        result = False

        if not os.path.exists(self.path):
            os.makedirs(self.path)

        logging.info("%s - Backuping into %s", repr(self.wp_site), self.path)

        try:
            self.generate_wp_files()
            self.generate_db_dump()
            logging.info("%s - %s WP backup is created", repr(self.wp_site), self.backup_pattern)
            result = True
        except WPException as err:
            logging.error("%s - WP backup failed: %s", repr(self.wp_site), err)

        if Utils.get_mandatory_env("WP_ENV") in settings.PROMETHEUS_OPENSHIFT_ENV_LIST:
            self.prometheus_monitoring(result, self.wp_site)

        return result

    def prometheus_monitoring(self, backup_status, wp_site):

        def my_auth_handler(url, method, timeout, headers, data):
            username = Utils.get_mandatory_env("PROMETHEUS_PUSHGATEWAY_USERNAME")
            password = Utils.get_mandatory_env("PROMETHEUS_PUSHGATEWAY_PASSWORD")
            return basic_auth_handler(url, method, timeout, headers, data, username, password)

        registry = CollectorRegistry()
        if backup_status:
            status = "backup OK"
        else:
            status = "backup KO"

        g = Gauge('backup_status', status, registry=registry)
        g.set_to_current_time()

        url = "https://os-wwp-metrics-pushgw.epfl.ch"

        job = "OpenShift_env:{} Site:{}".format(wp_site.openshift_env, wp_site.name)
        push_to_gateway(url, job=job, registry=registry, handler=my_auth_handler)
