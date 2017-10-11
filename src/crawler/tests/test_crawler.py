"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Testing the crawl.py script
"""
import os
import pytest
import requests_mock
import settings

from datetime import datetime
from importlib import reload
from crawler import JahiaConfig, SessionHandler

CURRENT_DIR = os.path.dirname(__file__)
TEST_FILE = 'existing-site_export_2017-10-11-05-03.zip'

TEST_SITE = "one-site"
TEST_USER = "foo"
TEST_PASSWORD = "bar"
TEST_HOST = "127.0.0.1"
TEST_ZIP_PATH = CURRENT_DIR


@pytest.fixture()
def delete_environment(request):
    """
        Delete all env. vars
    """
    for env_var in ["JAHIA_USER", "JAHIA_PASSWORD", "JAHIA_HOST", "JAHIA_ZIP_PATH"]:
        if os.environ.get(env_var):
            del os.environ[env_var]
    reload(settings)


@pytest.fixture()
def environment(request):
    """
    Load fake environment variables for every test
    """
    os.environ["JAHIA_HOST"] = TEST_HOST
    os.environ["JAHIA_USER"] = TEST_USER
    os.environ["JAHIA_PASSWORD"] = TEST_PASSWORD
    os.environ["JAHIA_ZIP_PATH"] = TEST_ZIP_PATH
    reload(settings)
    return os.environ


class TestConfig(object):

    def test_with_no_env(self, delete_environment):
        config = JahiaConfig(TEST_SITE)
        assert config.host == "localhost"

    def test_with_var_env(self, environment):
        config = JahiaConfig(TEST_SITE, date=datetime(2017, 10, 11, 5, 3))
        assert config.host == TEST_HOST
        assert config.file_url == "https://{}/administration/one-site_export_2017-10-11-05-03.zip".format(TEST_HOST)

    def test_config_with_kwargs(self, environment):
        config = JahiaConfig(TEST_SITE, host="epfl.ch", date=datetime(2017, 10, 11, 5, 3))
        assert config.host == "epfl.ch"
        assert config.file_url == "https://epfl.ch/administration/one-site_export_2017-10-11-05-03.zip"

    def test_existing_files(self, environment):
        config = JahiaConfig("existing-site")
        assert config.already_downloaded is True
        assert config.existing_files[-1].endswith(TEST_FILE)

    def test_non_existing_files(self):
        config = JahiaConfig(TEST_SITE)
        assert config.already_downloaded is False


class TestSession(object):

    def test_fail_with_missing_env(self, delete_environment):
        with pytest.raises(Exception):
            SessionHandler()

    def test_default_parameters(self, delete_environment):
        os.environ["JAHIA_PASSWORD"] = TEST_PASSWORD
        session = SessionHandler()
        assert session.username == settings.JAHIA_USER
        assert session.password == TEST_PASSWORD
        assert session.host == settings.JAHIA_HOST
        assert session.post_url == "https://{}/administration".format(settings.JAHIA_HOST)
        assert session.credentials == {
            'login_username': settings.JAHIA_USER,
            'login_password': TEST_PASSWORD
        }

    def test_session_with_kwargs(self, environment):
        session = SessionHandler(username="bob", password="bob's secret", host="epfl.ch")
        assert session.username == "bob"
        assert session.password == "bob's secret"
        assert session.host == "epfl.ch"
        assert session.post_url == "https://epfl.ch/administration"
        assert session.credentials == {
            'login_username': "bob",
            'login_password': "bob's secret"
        }

    def test_session(self):
        url = 'https://localhost/administration?redirectTo=%2Fadministration%3Fnull&do=processlogin'
        # data_file = 'session.data'
        # with requests_mock.Mocker() as mocker, open(data_file, 'r') as input:
        with requests_mock.Mocker() as mocker:
            # set mock response
            mocker.post(url, text="session")
            # make query
            session = SessionHandler(username=TEST_USER, password=TEST_PASSWORD)
            assert session._session is None
            assert session.session
            assert session._session is not None
