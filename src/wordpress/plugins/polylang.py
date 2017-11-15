import logging

from wordpress import WPException
from .config import WPPluginConfig


class WPPolylangConfig(WPPluginConfig):

    def configure(self, languages=None, default=None):
        """ kwargs:
            - `languages`, array: all languages to install
            - `default`, string: default language should be in `languages`
                If no default is provided, uses the first item of the array
        """
        # validate input (we keep en_GB instead of en_us to get UK flag, in admin)
        languages = languages or ["fr_FR", "en_GB"]
        default = default or languages[0]
        if default not in languages:
            raise WPException("Default language {} not found in list of supported languages {}".format(
                default, languages
            ))

        # adding languages
        for language in languages:
            is_default = 1 if language == default else 0
            command = "polylang language add {} {}".format(language, is_default)
            if not self.run_wp_cli(command):
                logging.warning("%s - could not install language %s", self.wp_site, language)
            else:
                logging.info("%s - installed language %s %s", self.wp_site, language, is_default and "[default]" or "")

        # configure options
        logging.info("%s - setting polylang options ...", self.wp_site)
        self.run_wp_cli("pll option update media_support 0")
        self.run_wp_cli("option update permalink_structure '/%postname%/'")

        # create menus
        logging.info("%s - creating polylang menu ...", self.wp_site)
        self.run_wp_cli("pll menu create Main top")
        self.run_wp_cli("pll menu create footer_nav footer_nav")

        # configure raw plugin
        super(WPPolylangConfig, self).configure()
