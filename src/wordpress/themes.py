import os
import shutil

from settings import WP_PATH

from .config import WPConfig


class WPThemeConfig(WPConfig):
    """ Relies on WPConfig to get wp_site and run wp-cli.
        Overrides is_installed to check for the theme only
    """

    THEMES_PATH = os.path.join('wp-content', 'themes')

    def __init__(self, wp_site, theme_name='epfl'):
        """
        Class constructor

        Argument keywords:
        wp_site -- Instance of class WPSite
        theme_name -- (optional) Theme name
        """
        super(WPThemeConfig, self).__init__(wp_site)
        self.name = theme_name
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
        # check if files are found in wp-content/themes
        return os.path.isdir(self.path)

    def install(self):
        """
        Install theme
        """
        # copy files into wp-content/themes
        src_path = os.path.sep.join([WP_PATH, self.THEMES_PATH, self.name])
        shutil.copytree(src_path, self.path)

    def activate(self):
        """
        Set theme as active theme in WordPress
        """
        # use wp-cli to activate theme
        return self.run_wp_cli('theme activate {}'.format(self.name))
