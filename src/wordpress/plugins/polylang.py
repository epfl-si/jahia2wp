import logging
import json
from wordpress import WPException
from .config import WPPluginConfig


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

    def _get_language_slug(self, locale):
        """
        Returns existing language slug based on given locale.
        Note: all languages have to be installed before calling this function

        Argument keywords
        locale -- Local of language for which we want the slug.
        """
        lang_list = json.loads(self.run_wp_cli("pll lang list --format=json"))
        # If no language installed
        if not lang_list:
            return None
        # Looping through existing languages
        for existing_language in lang_list:
            if existing_language['locale'] == locale:
                return existing_language['slug']
        return None

    def configure(self, force, languages=None, default=None, **kwargs):
        """ kwargs:
            - `languages`, array: all languages to install
            - `default`, string: default language should be in `languages`
                If no default is provided, uses the first item of the array
        """
        # validate input (we keep en_GB instead of en_us to get UK flag, in admin)
        # FIXME: Parameters are never given so it's always default languages that are taken
        languages = languages or ["fr_FR", "en_GB"]
        default = default or languages[0]
        if default not in languages:
            raise WPException("Default language {} not found in list of supported languages {}".format(
                default, languages
            ))

        # adding languages
        for language in languages:
            # If language doesn't already exists
            if not self._language_exists(language):
                command = "polylang language add {}".format(language)
                if not self.run_wp_cli(command):
                    logging.warning("%s - could not install language %s", self.wp_site, language)
                else:
                    logging.info("%s - installed language %s", self.wp_site, language)

        if force:
            # configure default language
            default = self._get_language_slug(default)
            logging.info("%s - setting default language to %s...", self.wp_site, default)
            self.run_wp_cli("pll option default {}".format(default))

        # configure options
        logging.info("%s - setting options...", self.wp_site)
        self.run_wp_cli("pll option update media_support 0")
        self.run_wp_cli("pll option sync taxonomies")

        # create menus if they don't exist
        logging.info("%s - creating polylang menu...", self.wp_site)
        if not self._menu_exists("Main"):
            self.run_wp_cli("pll menu create Main top")

        if not self._menu_exists("footer_nav"):
            self.run_wp_cli("pll menu create footer_nav footer_nav")

        # configure raw plugin
        super(WPPolylangConfig, self).configure(force)
