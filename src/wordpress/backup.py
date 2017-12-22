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


class WPBackup:
    """
    Class that handles the backups.

    There are 2 types of backup, that will be automatically made according the following :
    - full : Full backup, when no other full backup found in the NB_DAYS_BEFORE_NEW_FULL
    - inc : Incremental backup (for files only, not the DB), when full backup found (in NB_DAYS_BEFORE_NEW_FULL)

    A full backup generates 3 files :
    - "<wp_site_name>_<timestamp>.list": reference for incremental backup
    - "<wp_site_name>_<timestamp>_full.tar": to save of files
    - "<wp_site_name>_<timestamp>_full.sql": the db dump

    A incremental backup generates 2 files :
    - "<wp_site_name>_<timestamp>_inc.tar": to save files
    - "<wp_site_name>_<timestamp>_inc.sql": the db dump
    """

    FULL_PATTERN = "full"
    INCREMENTAL_PATTERN = "inc"
    TIMESTAMP_FORMAT = "%Y%m%d%H%M%S"
    # allow to override BACKUP_PATH in var env for travis (which uses a read-only system)
    BACKUP_PATH = Utils.get_optional_env("BACKUP_PATH", os.path.join(settings.DATA_PATH, 'backups'))

    def __init__(self, openshift_env, wp_site_url):
        """
        Class constructor

        Argument keywords:
        openshift_env -- Name of OpenShift environment on which script is executed
        wp_site_url -- URL to Website to backup
        """
        # validate input
        validate_openshift_env(openshift_env)
        URLValidator()(wp_site_url)

        # setup site and config
        self.wp_site = WPSite(openshift_env, wp_site_url)
        self.wp_config = WPConfig(self.wp_site)

        # set backup attributes
        self.datetime = datetime.datetime.now()
        self.timestamp = self.datetime.strftime(self.TIMESTAMP_FORMAT)
        self.path = os.path.join(self.BACKUP_PATH, self.wp_site.name)

        # set backup type, and name for list file
        listfile = self.get_daily_list()
        if listfile is None:
            self.backup_pattern = self.FULL_PATTERN
            self.listfile = os.path.join(
                self.path,
                "_".join((self.wp_site.name, self.timestamp)) + ".list")
        else:
            self.backup_pattern = self.INCREMENTAL_PATTERN
            self.listfile = os.path.join(self.path, listfile)

        # set filenames
        self.tarfile = os.path.join(
                self.path,
                "_".join((self.wp_site.name, self.timestamp, self.backup_pattern)) + ".tar")
        self.sqlfile = os.path.join(
                self.path,
                "_".join((self.wp_site.name, self.timestamp, self.backup_pattern)) + ".sql")

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
        logging.debug("{} - Seeking backups with regex: {}".format(repr(self.wp_site), file_regex))
        matches = [name for name in os.listdir(self.path)
                   if file_regex.search(name)]

        # returns oldest match
        if matches:
            matches.sort()
            logging.info("{} - Existing full backup {}. Making incremental new one".format(
                         repr(self.wp_site), matches[0]))
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

        logging.debug("{} - WP tar {} is created".format(repr(self.wp_site), self.tarfile))

    def generate_db_dump(self):
        """
        Generate the database dump.

        raises WPException on failures
        """
        command = "db export {} --path={}".format(self.sqlfile, self.wp_site.path)
        if not self.wp_config.run_wp_cli(command):
            raise WPException("WP DB dump {} could not be created".format(self.sqlfile))

        logging.debug("{} - WP db dump {} is created".format(repr(self.wp_site), self.sqlfile))

    def backup(self):
        """
        Launch the backup
        """
        if not os.path.exists(self.path):
            os.makedirs(self.path)

        logging.info("{} - Backuping into {}".format(repr(self.wp_site), self.path))

        try:
            self.generate_wp_files()
            self.generate_db_dump()
            logging.info("{} - {} WP backup is created".format(repr(self.wp_site), self.backup_pattern))
            return True

        except WPException as err:
            logging.error("{} - WP backup failed: {}".format(repr(self.wp_site), err))
            return False
