import logging
import json
from wordpress import WPException
from wordpress.plugins.config import WPPluginConfig
import settings


class WPPolylangConfig(WPPluginConfig):

    def _menu_exists(self, menu_name):
        """
        Tells if a menu exists

        Keyword arguments
        menu_name -- menu name to check if exists
        """
        menu_list = json.loads(self.run_wp_cli("menu list --fields=name --format=json"))
        # if no existing menus
        if not menu_list:
            return False
        # Looping through existing menus
        for existing_menu in menu_list:
            if existing_menu['name'] == menu_name:
                return True
        return False

    def _language_exists(self, locale):
        """
        Tells if a language exists

        Keyword arguments
        locale -- Local of the language (ex: en_GB, fr_FR, ...)
        """
        lang_list = json.loads(self.run_wp_cli("pll lang list --fields=locale --format=json"))
        # If no language installed
        if not lang_list:
            return False
        # Looping through existing languages
        for existing_language in lang_list:
            if existing_language['locale'] == locale:
                return True
        return False

    def configure(self, force, **kwargs):
        """ kwargs:
            - force -- True|False to tell if we have to erase configuration if already exists
        """
        # Retrieving languages slug list (short names)
        languages = self.config.config_custom.get('lang_list', None)
        if languages is None:
            raise WPException("Polylang - No 'lang_list' key found under 'config_custom' key in plugin YAML file")

        if not languages:
            raise WPException("Polylang - Empty language list")

        languages = languages.split(',')

        # First language is default
        default = languages[0]

        # adding languages
        for language in languages:
            # If language is not supported
            if language not in settings.SUPPORTED_LANGUAGES:
                raise WPException("Polylang - Language not supported: {}".format(language))

            # Getting language locale (needed by Polylang 'pll' WPCLI command)
            language_locale = settings.SUPPORTED_LANGUAGES[language]
            # If language locale doesn't already exists
            if not self._language_exists(language_locale):
                command = "polylang language add {}".format(language_locale)
                if not self.run_wp_cli(command):
                    logging.warning("{} - Polylang - Could not install language {}".format(self.wp_site, language))
                else:
                    logging.info("{} - Polylang - Language installed: {}".format(self.wp_site, language))

        if force:
            # configure default language (using slug, not using locale)
            logging.info("{} - Polylang - Setting default language to {}...".format(self.wp_site, default))
            self.run_wp_cli("pll option default {}".format(default))

        # configure options
        logging.info("{} - Polylang - Setting options...".format(self.wp_site))
        self.run_wp_cli("pll option update media_support 0")
        self.run_wp_cli("pll option sync taxonomies")

        # create menus if they don't exist
        logging.info("{} - Polylang - Creating menus...".format(self.wp_site))
        if not self._menu_exists(settings.MAIN_MENU):
            self.run_wp_cli("pll menu create {} top".format(settings.MAIN_MENU))

        if not self._menu_exists(settings.FOOTER_MENU):
                self.run_wp_cli("pll menu create {} footer_nav".format(settings.FOOTER_MENU))

        # configure raw plugin
        super(WPPolylangConfig, self).configure(force)
