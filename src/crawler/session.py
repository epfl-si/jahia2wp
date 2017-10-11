""" (c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Helper class which creates and persist a session to a Jahia site
"""
import logging
import requests

from utils import Utils

from settings import JAHIA_USER, JAHIA_HOST, JAHIA_URI


class SessionHandler(object):

    def __init__(self, username=None, password=None, host=None):
        # credentials to use
        self.username = username or JAHIA_USER
        self.password = password or Utils.get_mandatory_env("JAHIA_PASSWORD")

        # crawling parameters for HTTP request
        self.host = host or JAHIA_HOST
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
            logging.info("%s - authenticating...", self.post_url)
            session = requests.Session()
            response = session.post(
                self.post_url,
                params=self.id_get_params,
                data=self.credentials
            )

            # log and set session
            logging.info("%s - requested %s", self.post_url, response.url)
            logging.debug("%s - returned %s", self.post_url, response.status_code)
            self._session = session

        # cls.session is set, return it
        return self._session

    @property
    def post_url(self):
        return "https://{}/{}".format(self.host, JAHIA_URI)

    @property
    def credentials(self):
        return {
            'login_username': self.username,
            'login_password': self.password
        }
