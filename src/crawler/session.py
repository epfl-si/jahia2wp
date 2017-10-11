""" (c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Helper class which creates and persist a session to a Jahia site
"""
import logging
import requests

from utils import Utils

# do not explicitely import variables
# this allows to reload settings at running time with different environment variable (e.g in tests)
import settings


class SessionHandler(object):

    def __init__(self, username=None, password=None, host=None):
        # credentials to use
        self.username = username or settings.JAHIA_USER
        self.password = password or Utils.get_mandatory_env("JAHIA_PASSWORD")

        # crawling parameters for HTTP request
        self.host = host or settings.JAHIA_HOST
        self.id_get_params = {
            'do': 'processlogin',
            'redirectTo': '/administration?null'
        }

        # lazy initialization
        self._session = None

    @property
    def session(self):
        """ Make a POST on Jahia administration to get a valid session """
        if self._session is None:
            # lazy initialization
            logging.info("%s - authenticating %s...", self.post_url, self.username)
            session = requests.Session()
            response = session.post(
                self.post_url,
                data=self.credentials,
                params=self.id_get_params,
            )

            # log and set session
            logging.debug("%s => %s", response.url, response.status_code)
            self._session = session

        # cls.session is set, return it
        return self._session

    @property
    def post_url(self):
        return "{}://{}/{}".format(settings.JAHIA_PROTOCOL, self.host, settings.JAHIA_URI)

    @property
    def credentials(self):
        return {
            'login_username': self.username,
            'login_password': self.password
        }
