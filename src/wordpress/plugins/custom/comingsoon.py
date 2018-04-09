import logging
import json
from wordpress.plugins.config import WPPluginConfig


class WPComingSoonConfig(WPPluginConfig):

    def configure(self, force, **kwargs):
        """ kwargs:
            - force -- True|False to tell if we have to erase configuration if already exists
        """

        # configure options
        logging.info("{} - ComingSoon - Setting options...".format(self.wp_site))

        # Loading current configuration (which is an empty hashtable with configuration options)
        option = json.loads(self.run_wp_cli('option get seed_csp4_settings_content --format=json'))

        # Setting options
        option['logo'] = 'https://mediacom.epfl.ch/files/content/sites/mediacom/files/EPFL-Logo.jpg'
        option['headline'] = 'Something new is coming...'
        # Building WP-ADMIN URL from WP site URL.
        option['description'] = '&nbsp;<div class="footer-content"><nav class="footer-navigation" role="navigation"> \
</nav><p class="site-admin" style="position:absolute;bottom:0;width:50%;text-align:right;"><span style="font-size:10pt;\
font-family:arial,helvetica,sans-serif;"><a href="{}/wp-admin/">Connexion / Login</a></span></p></div>'.format(
            self.wp_site.url
        )
        option['footer_credit'] = '1'

        # If we have to force update, we display "ComingSoon" screen
        if force:
            option['status'] = '1'

        self.run_wp_cli("option update seed_csp4_settings_content --format=json ", pipe_input=json.dumps(option))

        # configure raw plugin
        super(WPComingSoonConfig, self).configure(force)
