import datetime
import logging
import os
import re
import shutil

from . import WPUtils, WPException
from utils import Utils


class WPBackup:
    """
    Class that handles the backups.
    
    A backup of a WordPress site contains :
    - copy of all .php file (tar files) 
    - a dump of the database (.sql file)
    
    Backup types:
    There are 2 types of backup :
    - full : Full backup.
    - inc : Incremental backup.
    
    A full backup generates 3 files :
    - ".tar" file: to save of php files. 
      Format : <wp_site_id>_<wp_site_folder>_<timestamp>_fullN.tar
      
    - ".list" file: uses for incremental backup
     Format : <wp_site_id>_<wp_site_folder>_fullN.list
     
    - ".sql" file: the db dump
    Format : <wp_site_id>_<wp_site_folder>_<timestamp>_fullN.sql
    
    A incremental backup generates 2 files :
    - ".tar" file: to save of php files. 
      Format : <wp_site_id>_<wp_site_folder>_<timestamp>_fullN_incM.tar
      
    - ".sql" file: the db dump
    Format : <wp_site_id>_<wp_site_folder>_<timestamp>_fullN.sql
    
    The content difference between incremental tar files are saved in the list file.
    """

    BACKUP_ROOT_DIR = "../data/backups/"
    REGEX_FULL_NUMBER = ".+full([0-9]+)\.tar$"
    REGEX_INC_NUMBER = ".+full{}_inc([0-9]+)\.tar$"

    def __init__(self, wp_site, backup_type):

        self.wp_site = wp_site
        self.type = backup_type

        if not self.wp_site.folder:
            self.dir_name = "localhost"
        else:
            self.dir_name = self.wp_site.folder

        # Create a backup folder data/backups/wp_site_id
        self.path = os.path.join(
            self.BACKUP_ROOT_DIR,
            self.wp_site.wp_site_id
        )

    def _get_max_number(self, regex):
        """
        Go through the backup directory and return the max number 
        according to the regular expression passed as parameter
        """
        pattern = re.compile(regex)

        number_list = [pattern.findall(file)[0] for file in os.listdir(self.path) if pattern.match(file)]

        if not number_list:
            return str(0)

        return max(number_list)

    @property
    def max_full_number(self):
        """
        Go through the backup directory and return the max full number 
        """
        return self._get_max_number(regex=self.REGEX_FULL_NUMBER)

    @property
    def max_inc_number(self):
        """
        Go through the backup directory and return the max inc number
        """

        if self.max_full_number == 0:
            raise WPException("Incremental backup is based on full backup. But there is no full backup")

        return self._get_max_number(regex=self.REGEX_INC_NUMBER.format(self.max_full_number))

    @property
    def next_inc_number(self):
        """
        Calculate the next inc number
        """

        # check if backup directory is empty
        if not os.listdir(self.path):
            raise WPException("Incremental backup is based on full backup. But there is no full backup")

        return str(int(self.max_inc_number) + 1)

    @property
    def next_full_number(self):
        """
        Calculate the next full number
        """

        # check if backup directory is empty
        if not os.listdir(self.path):
            return str(1)

        # return the next full number
        return str(int(self.max_full_number) + 1)

    def generate_listed_incremental_file_name(self, tar_file_name):
        """
        Generate the name of listed incremental file.
        This file is used to save the content differences between the different incremental tar files
        """

        if self.type == "full":
            pattern = re.compile(self.REGEX_FULL_NUMBER)
            current_full_number = pattern.findall(tar_file_name)[0]
        elif self.type == "inc":
            pattern = re.compile(self.REGEX_INC_NUMBER.format(self.max_full_number))
            current_full_number = pattern.findall(tar_file_name)[0]
        listed_incremental_file = "_".join(
            (self.wp_site.wp_site_id, self.dir_name, "full")
        )
        listed_incremental_file += "".join(
            (current_full_number, ".list")
        )
        return listed_incremental_file

    def generate_wp_files_backup(self, tar_file_name):
        """
        Generate a tar file that contains the php files of the WordPress site
        """
        # Generate tar file
        Utils.generate_tar_file(
            backup_file=os.path.join(self.path, tar_file_name),
            backup_listed_incremental_file=os.path.join(
                self.path,
                self.generate_listed_incremental_file_name(tar_file_name)
            ),
            source_path=self.wp_site.path
        )

        logging.debug("{} - WP tar {} is created".format(repr(self.wp_site), tar_file_name))

    def generate_db_dump(self, dump_file_name):
        """
        Generate the database dump.
        """

        # Build db backup
        command = "db export {} --path={}".format(
            dump_file_name,
            self.wp_site.path
        )
        WPUtils.run_wp_cli(command, self.wp_site.path)

        # Move dump to the backup folder
        source = os.path.abspath(dump_file_name)
        destination = os.path.join(os.getcwd(), self.path, dump_file_name)
        shutil.move(source, destination)

        logging.debug("{} - WP db dump {} is created".format(repr(self.wp_site), dump_file_name))

    def generate_backup(self):
        """
        Generate the backup 
        """

        if not os.path.exists(self.path):
            os.makedirs(self.path)
        logging.debug("{} - Backup folder: {}".format(repr(self.wp_site), self.path))

        timestamp = datetime.datetime.now().strftime("%Y%m%d%H%M%S")
        base_name = "_".join((self.wp_site.wp_site_id, self.dir_name, timestamp, 'full'))

        if self.type == "full":
            base_name += self.next_full_number

        elif self.type == "inc":
            base_name += "".join((self.max_full_number, "_inc", self.next_inc_number))

        self.generate_wp_files_backup(tar_file_name=base_name + ".tar")
        self.generate_db_dump(dump_file_name=base_name + ".sql")

        logging.info("{} - WP backup is created".format(repr(self.wp_site)))
