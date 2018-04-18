import logging
import json
from wordpress import WPException
from wordpress.plugins.config import WPPluginConfig
import settings
import html


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

    def _update_taglines(self, languages):
        """
        Update tagline for all languages. This information is stored in "postmeta" table and we have to
        gather some information in another table (post) before we can update string translation.
        :param languages: list of languages. Default one is in first place.
        :return:
        """

        options = []

        # We get the configured names because they are used by Polylang to match translations to use.
        tagline_key = self.run_wp_cli("option get blogdescription")
        site_title_key = self.run_wp_cli("option get blogname")
        date_format_key = self.run_wp_cli("option get date_format")
        time_format_key = self.run_wp_cli("option get time_format")

        for lang in languages:
            # tagline for current lang, can be a string (if default value used) or a dict.
            # If it's a string, we take the same tagline for all languages
            lang_tagline = self.wp_site.wp_tagline[lang] if isinstance(self.wp_site.wp_tagline, dict) else \
                self.wp_site.wp_tagline
            # Transforming special characters
            lang_tagline = html.escape(lang_tagline, quote=True).encode('ascii', 'xmlcharrefreplace').decode()

            # Adding option for current lang
            # The first option of each list is the key to find translation. Only the tagline is updated, all others
            # information are the same as the one defined in the default language.
            options.append([[site_title_key, site_title_key],
                            [tagline_key, lang_tagline],
                            [date_format_key, date_format_key],
                            [time_format_key, time_format_key]])

        # Listing post associated to Polylang translations
        post_ids = self.run_wp_cli("post list --post_type=polylang_mo --field=ID --format=csv")

        # Looping through post IDs to update string translations. We have to sort the list to loop correctly through
        # languages. First one is the default and others are following. Sorting the list ensure that we update options
        # in the correct order
        for post_id in sorted(post_ids.split('\n')):

            cmd = "post meta update {} _pll_strings_translations --format=json".format(post_id)
            # Getting next option
            option = options.pop(0)

            if not self.run_wp_cli(cmd, pipe_input=json.dumps(option)):
                logging.warning("{} - Polylang - Cannot add string translation for post {}".format(self.wp_site,
                                                                                                   post_id))

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
            # Updating taglines
            self._update_taglines(languages)

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
