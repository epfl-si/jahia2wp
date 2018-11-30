import logging
from wordpress.plugins.config import WPPluginConfig
from utils import Utils


class WPCloudFlareConfig(WPPluginConfig):

    def configure(self, force, **kwargs):
        """ kwargs:
            - force -- True|False to tell if we have to erase configuration if already exists
        """

        cf_api_email = Utils.get_mandatory_env(key="CLOUDFLARE_API_EMAIL")
        cf_api_key = Utils.get_mandatory_env(key="CLOUDFLARE_API_KEY")

        # configure options
        logging.info("{} - CloudFlare - Setting options...".format(self.wp_site))

        self.run_wp_cli("option update cloudflare_api_key {}".format(cf_api_key))
        self.run_wp_cli("option update cloudflare_api_email {}".format(cf_api_email))
        self.run_wp_cli("option update cloudflare_cached_domain_name {}".format(self.wp_site.domain))

        # configure raw plugin
        super(WPCloudFlareConfig, self).configure(force)
