"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Testing the crawl.py script
"""
import os
import pytest
import requests_mock

from settings import DEFAULT_JAHIA_USER, DEFAULT_JAHIA_HOST
from crawler import JahiaConfig, SessionHandler

TEST_SITE = "one-site"
TEST_USER = "foo"
TEST_PASSWORD = "bar"
TEST_HOST = "127.0.0.1"
TEST_ZIP_PATH = "."


@pytest.fixture()
def delete_environment(request):
    """
        Delete all env. vars
    """
    for env_var in ["JAHIA_USER", "JAHIA_PASSWORD", "JAHIA_HOST", "JAHIA_ZIP_PATH"]:
        if os.environ.get(env_var):
            del os.environ[env_var]


@pytest.fixture()
def environment(request):
    """
    Load fake environment variables for every test
    """
    os.environ["JAHIA_HOST"] = TEST_HOST
    os.environ["JAHIA_USER"] = TEST_USER
    os.environ["JAHIA_PASSWORD"] = TEST_PASSWORD
    os.environ["JAHIA_ZIP_PATH"] = TEST_ZIP_PATH
    return os.environ


class TestConfig(object):

    def test_fail_with_missing_env(self, delete_environment):
        with pytest.raises(Exception):
            JahiaConfig(TEST_SITE)

    def test_default_parameters(self, delete_environment):
        os.environ["JAHIA_PASSWORD"] = TEST_PASSWORD
        config = JahiaConfig(TEST_SITE)
        assert config.username == DEFAULT_JAHIA_USER
        assert config.password == TEST_PASSWORD
        assert config.host == DEFAULT_JAHIA_HOST
        assert config.post_url == "https://localhost/administration"
        assert config.credentials == {
            'login_username': DEFAULT_JAHIA_USER,
            'login_password': TEST_PASSWORD
        }

    def test_with_var_env(self, environment):
        config = JahiaConfig(TEST_SITE)
        assert config.username == TEST_USER
        assert config.password == TEST_PASSWORD
        assert config.host == TEST_HOST
        assert config.post_url == "https://{}/administration".format(TEST_HOST)
        assert config.credentials == {
            'login_username': TEST_USER,
            'login_password': TEST_PASSWORD
        }

    def test_config_with_kwargs(self, environment):
        config = JahiaConfig(TEST_SITE, username="bob", password="bob's secret", host="epfl.ch")
        assert config.username == "bob"
        assert config.password == "bob's secret"
        assert config.host == "epfl.ch"
        assert config.post_url == "https://epfl.ch/administration"
        assert config.credentials == {
            'login_username': "bob",
            'login_password': "bob's secret"
        }


class TestSession(object):

    def test_default_parameters(self):
        url = 'https://127.0.0.1/administration?redirectTo=%2Fadministration%3Fnull&do=processlogin'
        # data_file = 'session.data'
        # with requests_mock.Mocker() as mocker, open(data_file, 'r') as input:
        with requests_mock.Mocker() as mocker:
            # set mock response
            mocker.post(url, text="session")
            # make query
            session = SessionHandler(TEST_USER, TEST_PASSWORD)
            assert session._session is None
            assert session.session
            assert session._session is not None
