""" (c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Helper class which holds all configuration parameters to connect to Jahia
"""
from settings import DEFAULT_JAHIA_USER, DEFAULT_JAHIA_HOST, DEFAULT_JAHIA_ZIP_PATH

from utils import Utils


class JahiaConfig(object):

    # where to store zip files
    EXPORT_PATH = Utils.get_optional_env("JAHIA_ZIP_PATH", DEFAULT_JAHIA_ZIP_PATH)

    def __init__(self, site, username=None, password=None, host=None):
        # site to crawl (jahia key)
        self.site = site
        self.host = host or Utils.get_optional_env("JAHIA_HOST", DEFAULT_JAHIA_HOST)

        # credentials to use
        self.username = username or Utils.get_optional_env("JAHIA_USER", DEFAULT_JAHIA_USER)
        self.password = password or Utils.get_mandatory_env("JAHIA_PASSWORD")

        # crawling parameters for HTTP request
        self.uri = "administration"
        self.file_pattern = "%s_export_%s.zip"

        self.id_get_params = {
            'do': 'processlogin',
            'redirectTo': '/administration?null'
        }
        self.dwld_get_params = {
            'do': 'sites',
            'sub': 'multipledelete',
            'exportformat': 'site'
        }

    @property
    def post_url(self):
        return "https://{}/{}".format(self.host, self.uri)

    @property
    def credentials(self):
        return {
            'login_username': self.username,
            'login_password': self.password
        }
