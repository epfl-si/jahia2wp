"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Testing the crawl.py script
"""
import requests_mock

from crawler import JahiaConfig, SessionHandler

TEST_USER = "foo"
TEST_PASSWORD = "bar"
TEST_HOST = "127.0.0.1"


class TestConfig(object):

    def test_default_parameters(self):
        config = JahiaConfig(TEST_USER, TEST_PASSWORD)
        assert config.username == TEST_USER
        assert config.password == TEST_PASSWORD
        assert config.host == "localhost"
        assert config.post_url == "localhost/administration"
        assert config.credentials == {
            'login_username': TEST_USER,
            'login_password': TEST_PASSWORD
        }

    def test_config_with_host(self):
        config = JahiaConfig(TEST_USER, TEST_PASSWORD, host=TEST_HOST)
        assert config.host == TEST_HOST
        assert config.post_url == "{}/administration".format(TEST_HOST)


class TestSession(object):

    def test_default_parameters(self):
        url = 'https://localhost/'
        data_file = 'session.data'
        with requests_mock.Mocker() as mocker, open(data_file, 'r') as input:
            # set mock response
            mocker.get(url, text=input.read())
            # make query
            session = SessionHandler(TEST_USER, TEST_PASSWORD)
            assert session._session is None
            assert session.session
            assert session._session is not None
