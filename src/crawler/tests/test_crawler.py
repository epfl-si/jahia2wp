"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Testing the crawl.py script
"""
import os
import pytest
import requests_mock
import settings

from datetime import datetime
from importlib import reload
from crawler import JahiaConfig, SessionHandler, JahiaCrawler, download_many

CURRENT_DIR = os.path.dirname(__file__)
TEST_FILE = "one-site_export_2017-10-11-05-03.zip"
TEST_SITE = "one-site"
TEST_USER = "foo"
TEST_PASSWORD = "bar"
TEST_HOST = "localhost"


@pytest.fixture()
def delete_environment(request):
    """
        Delete all env. vars
    """
    for env_var in ["JAHIA_USER", "JAHIA_PASSWORD", "JAHIA_HOST"]:
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
    reload(settings)
    return os.environ


@pytest.fixture(scope='module')
def session_handler(request):
    url = '{}://localhost/administration?redirectTo=%2Fadministration%3Fnull&do=processlogin'\
        .format(settings.JAHIA_PROTOCOL)
    # data_file = 'session.data'
    # with requests_mock.Mocker() as mocker, open(data_file, 'r') as input:
    with requests_mock.Mocker() as mocker:
        # set mock response
        mocker.post(url, text="session")
        # make query
        handler = SessionHandler(username=TEST_USER, password=TEST_PASSWORD)
        handler.session
        return handler


class TestConfig(object):

    def test_with_no_env(self, delete_environment):
        config = JahiaConfig(TEST_SITE)
        assert config.host == "localhost"

    def test_with_var_env(self, environment):
        config = JahiaConfig(TEST_SITE, date=datetime(2017, 10, 11, 5, 3))
        assert config.host == TEST_HOST
        assert config.file_url == "{}://{}/{}/one-site_export_2017-10-11-05-03.zip"\
            .format(settings.JAHIA_PROTOCOL, TEST_HOST, JahiaConfig.JAHIA_DOWNLOAD_URI)

    def test_config_with_kwargs(self, environment):
        config = JahiaConfig(TEST_SITE, host="epfl.ch", date=datetime(2017, 10, 11, 5, 3))
        assert config.host == "epfl.ch"
        assert config.file_url == "{}://epfl.ch/{}/one-site_export_2017-10-11-05-03.zip"\
            .format(settings.JAHIA_PROTOCOL, JahiaConfig.JAHIA_DOWNLOAD_URI)

    def test_existing_files(self, environment):
        config = JahiaConfig(TEST_SITE, zip_path=CURRENT_DIR)
        assert config.already_downloaded is True
        assert config.existing_files[-1].endswith(TEST_FILE)

    def test_non_existing_files(self):
        config = JahiaConfig("not-downloaded-site", zip_path=CURRENT_DIR)
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
        assert session.post_url == "{}://{}/administration".format(settings.JAHIA_PROTOCOL, settings.JAHIA_HOST)
        assert session.credentials == {
            'login_username': settings.JAHIA_USER,
            'login_password': TEST_PASSWORD
        }

    def test_session_with_kwargs(self, environment):
        session = SessionHandler(username="bob", password="bob's secret", host="epfl.ch")
        assert session.username == "bob"
        assert session.password == "bob's secret"
        assert session.host == "epfl.ch"
        assert session.post_url == "{}://epfl.ch/administration".format(settings.JAHIA_PROTOCOL)
        assert session.credentials == {
            'login_username': "bob",
            'login_password': "bob's secret"
        }

    def test_session(self, session_handler):
            assert session_handler.session
            assert session_handler._session is not None


class TestCrawler(object):

    def test_download_existing(self, session_handler):
        crawler = JahiaCrawler(TEST_SITE, zip_path=CURRENT_DIR)
        assert crawler.download_site().endswith(TEST_FILE)

    def test_download_non_existing(self, session_handler):
        url = '{}://localhost/{}/non-existing-site_export_2017-10-11-05-03.zip?' \
              'do=sites&sitebox=non-existing-site&exportformat=site' \
              .format(settings.JAHIA_PROTOCOL, JahiaConfig.JAHIA_DOWNLOAD_URI)
        zip_path = os.path.join(CURRENT_DIR, TEST_FILE)
        with requests_mock.Mocker() as mocker, open(zip_path, 'rb') as input:
            # set mock response
            mocker.post(url, body=input)
            # make query
            crawler = JahiaCrawler("non-existing-site", session=session_handler, date=datetime(2017, 10, 11, 5, 3))
            downloaded_path = crawler.download_site()
            assert downloaded_path.endswith('non-existing-site_export_2017-10-11-05-03.zip')
            os.remove(downloaded_path)

    def test_download_many(self, session_handler):
        assert TEST_SITE in download_many([TEST_SITE], zip_path=CURRENT_DIR, session=session_handler)
