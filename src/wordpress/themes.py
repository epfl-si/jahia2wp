import os
import settings
import shutil
from io import BytesIO
from zipfile import ZipFile
from urllib.request import urlopen

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
        if theme_name == 'epfl':
            self.name = 'wp-theme-2018'
        elif theme_name == 'epfl-light':
            self.name = 'wp-theme-light'

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
        """
        zip_url = "https://github.com/epfl-idevelop/wp-theme-2018/archive/wp-theme-light.zip"
        zip_base_name = 'wp-theme-2018-wp-theme-light/'

        # unzip in memor
        resp = urlopen(zip_url)
        with ZipFile(BytesIO(resp.read())) as zipObj:
            zipObj.extractall(path=self.base_path)

        # clean the extracted mess, aka correct folders and remove unused one
        shutil.move(os.path.join(self.base_path, zip_base_name, 'wp-theme-2018'),
                    os.path.join(self.base_path, 'wp-theme-2018'))
        shutil.move(os.path.join(self.base_path, zip_base_name, 'wp-theme-light'),
                    os.path.join(self.base_path, 'wp-theme-light'))
        shutil.rmtree(os.path.join(self.base_path, zip_base_name))
