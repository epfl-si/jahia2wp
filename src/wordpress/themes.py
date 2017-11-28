import os
import shutil

import settings

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

    @classmethod
    def install_all(cls, wp_site):
        """
        Install all themes

        Argument keywords:
        wp_site -- Instance of class WPSite
        """
        # Generate path to source themes folder
        src_theme_path = os.path.sep.join([settings.WP_FILES_PATH, cls.THEMES_PATH])

        # Looping through folder elements
        for theme_folder in os.listdir(src_theme_path):
            # Generate path to current element
            current_theme_path = os.path.join(src_theme_path, theme_folder)
            # If current element is a directory, it is a theme
            if os.path.isdir(current_theme_path):

                # Copy current theme files into wp-content/themes
                shutil.copytree(current_theme_path,
                                os.path.sep.join([wp_site.path, cls.THEMES_PATH, theme_folder]))

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
