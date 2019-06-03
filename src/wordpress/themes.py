import os
import settings
import logging
import shutil
import zipfile
from io import BytesIO
from zipfile import ZipFile
from urllib.request import urlopen
from utils import Utils

from .config import WPConfig


class WPThemeConfig(WPConfig):
    """ Relies on WPConfig to get wp_site and run wp-cli.
        Overrides is_installed to check for the theme only
    """

    THEMES_PATH = os.path.join('wp-content', 'themes')

    def __init__(self, wp_site, theme_name=settings.DEFAULT_THEME_NAME, theme_faculty=None):
        """
        Class constructor

        Argument keywords:
        wp_site -- Instance of class WPSite
        theme_name -- (optional) Theme name, converted trough a mapping
        theme_faculty -- (optional) Theme faculty. Used for theme color.
        """
        super(WPThemeConfig, self).__init__(wp_site)

        # convert theme name
        self.name = ''
        if theme_name == 'epfl':
            self.name = 'wp-theme-2018'
        elif theme_name == 'epfl-light':
            self.name = 'wp-theme-light'
        else:
            self.name = theme_name

        self.faculty = theme_faculty
        self.base_path = os.path.sep.join([self.wp_site.path, self.THEMES_PATH])
        self.path = os.path.sep.join([self.base_path, self.name])

    def __repr__(self):
        installed_string = '[ok]' if self.is_installed else '[ko]'
        return "theme {0} at {1}".format(installed_string, self.path)

    @property
    def is_installed(self):
        """
        Tells if theme is installed or not

        Return
        True, False
        """
        command = "theme list --field=name --format=json"
        command_output = self.run_wp_cli(command)
        return False if command_output is True else self.name in command_output

    def activate(self):
        """
        Set theme as active theme in WordPress
        """
        # use wp-cli to activate theme
        result = self.run_wp_cli('theme activate {}'.format(self.name))

        if not result:
            return False

        if self.faculty is None:
            return result
        else:
            return self.run_wp_cli('option add epfl:theme_faculty {}'.format(self.faculty))

    def install(self, force_reinstall=False):
        """
        Install and activate 2018 theme

        To do this, we download archive from GitHub (where theme last version is located), we extract it and then
        we create a new ZIP file per theme we want to install and we finally use WP-CLI command to install from
        created ZIP files.
        In the past, we use to just copy the extracted themes files to the correct location but we lose the possibility
        to symlink it if it exists in WordPress image. WP-CLI has been modified to handle theme installation and create
        symlink if needed so we need to use WP-CLI command to install themes to make this work.
        But this code will still work, even if we don't have a modified WP-CLI version.
        """
        zip_url = "https://github.com/epfl-idevelop/wp-theme-2018/archive/master.zip"
        zip_base_name = 'wp-theme-2018-master/'

        logging.debug("Downloading themes package...")
        # unzip in memory
        resp = urlopen(zip_url)
        with ZipFile(BytesIO(resp.read())) as zipObj:
            zipObj.extractall(path=self.base_path)

        # Get current working directory to come back here after compress operation.
        initial_working_dir = os.getcwd()

        # Going into theme parent directory to have only theme folder in ZIP file (otherwise, we have full path
        # to theme directory...)
        os.chdir(os.path.join(self.base_path, zip_base_name))

        for theme_name in ['wp-theme-2018', 'wp-theme-light']:

            logging.debug("Installing theme %s...", theme_name)

            # Generating ZIP file name
            zip_name = "{}.{}.zip".format(theme_name, Utils.generate_name(10))
            # We put zip file in the same directory as all extracted files
            zip_full_path = os.path.join(self.base_path, zip_name)
            theme_zip = zipfile.ZipFile(zip_full_path, 'w', zipfile.ZIP_DEFLATED)

            for root, dirs, files in os.walk(theme_name):
                for file in files:
                    theme_zip.write(os.path.join(root, file))

            theme_zip.close()

            force_option = "--force" if force_reinstall else ""
            command = "theme install {} {} ".format(force_option, zip_full_path)
            self.run_wp_cli(command)

        os.chdir(initial_working_dir)

        # clean the extracted mess and generated zip files, aka correct folders and remove unused one
        shutil.rmtree(os.path.join(self.base_path, zip_base_name))
