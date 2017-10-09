""" (c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Helper class which holds all configuration parameters to connect to Jahia
"""
from utils import Utils


class JahiaConfig(object):

    def __init__(self, username, password, host=None):
        # volatile parameter
        self.username = username
        self.password = password
        self.host = host or Utils.get_optional_env("JAHIA_HOST", "localhost")

        # jahia parameters
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
        return "{}/{}".format(self.host, self.uri)

    @property
    def credentials(self):
        return {
            'login_username': self.username,
            'login_password': self.password
        }
