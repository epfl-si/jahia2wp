""" (c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Helper class which creates and persist a session to a Jahia site
"""
import logging
import requests

from .configurator import JahiaConfig


class SessionHandler(object):

    def __init__(self, username, password, host=None):
        self.config = JahiaConfig(username, password, host=host)

        # lazy initialization
        self._session = None

    @property
    def session(self):
        """ Make a POST on Jahia administration to get a valid session """
        if self._session is None:
            # lazy initialization
            logging.info("%s - authenticating...", self.config.post_url)
            session = requests.Session()
            response = session.post(
                self.config.post_url,
                params=self.config.id_get_params,
                data=self.config.credentials
            )

            # log and set session
            logging.info("%s - requested %s", self.config.post_url, response.url)
            logging.debug("%s - returned %s", self.config.post_url, response.status_code)
            self._session = session

        # cls.session is set, return it
        return self._session
