import os
import shutil
import zipfile
import settings

from .config import WPConfig
from utils import Utils


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
        theme_name -- (optional) Theme name
        theme_faculty -- (optional) Theme faculty. Used for theme color.
        """
        super(WPThemeConfig, self).__init__(wp_site)
        self.name = theme_name
        self.faculty = theme_faculty
        self.path = os.path.sep.join([self.wp_site.path, self.THEMES_PATH, theme_name])

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

    def install_all(self):
        """
        Install all themes
        """
        # Directory creation if not exists
        if not os.path.exists(settings.THEME_ZIP_PATH):
            os.makedirs(settings.THEME_ZIP_PATH)

        # Generate path to source themes folder
        src_theme_path = os.path.sep.join([settings.WP_FILES_PATH, self.THEMES_PATH])

        # Get current working directory to come back here after compress operation.
        initial_working_dir = os.getcwd()

        # Going into theme parent directory to have only theme folder in ZIP file (otherwise, we have full path
        # to theme directory...)
        os.chdir(src_theme_path)

        # Looping through folder elements
        for theme_folder in os.listdir(src_theme_path):

            # If it's not a theme directory, we skip it
            if not os.path.isdir(theme_folder):
                continue

            # Generating ZIP file name
            zip_name = "{}.{}.zip".format(theme_folder, Utils.generate_name(10))
            # We put zip file in the same directory as all extracted files
            zip_full_path = os.path.join(settings.THEME_ZIP_PATH, zip_name)
            theme_zip = zipfile.ZipFile(zip_full_path, 'w', zipfile.ZIP_DEFLATED)

            for root, dirs, files in os.walk(theme_folder):
                for file in files:
                    theme_zip.write(os.path.join(root, file))

            theme_zip.close()

            command = "theme install {} ".format(zip_full_path)
            self.run_wp_cli(command)

            # Cleaning ZIP file
            os.remove(zip_full_path)

        os.chdir(initial_working_dir)

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
