import pytest

import settings
from wordpress import WPSite, WPUser, WPConfig
from wordpress.generator import MockedWPGenerator

ROOT_PATH = '/srv/{}/localhost/'.format(settings.OPENSHIFT_ENV)


class TestWPSite:

    @pytest.fixture(scope='module')
    def wordpress(self):
        return WPSite(
            openshift_env=settings.OPENSHIFT_ENV,
            wp_site_url="http://localhost/folder",
            wp_site_title="TST",
            wp_tagline={'en': "Test site"})

    def test_path(self, wordpress):
        assert wordpress.path == ROOT_PATH + "htdocs/folder"

    def test_url(self, wordpress):
        assert wordpress.url == "https://localhost/folder"

    def test_name(self, wordpress):
        assert wordpress.name == "folder"
        assert WPSite(
            openshift_env=settings.OPENSHIFT_ENV,
            wp_site_url="http://localhost/") \
            .name == "localhost"
        assert WPSite(
            openshift_env=settings.OPENSHIFT_ENV,
            wp_site_url="http://www.epfl.ch/") \
            .name == "www"

    def test_failing_url_from_path(self):
        with pytest.raises(ValueError):
            WPSite.from_path("/usr/folder")

    def test_valid_url_from_path(self):
        assert WPSite.from_path(ROOT_PATH + "htdocs/folder") \
            .url == "https://localhost/folder"
        assert WPSite.from_path(ROOT_PATH + "htdocs/folder/sub") \
            .url == "https://localhost/folder/sub"


class TestWPUser:

    def test_password(self):
        user = WPUser(settings.OPENSHIFT_ENV, "test@example.com")
        assert user.password is None
        user.set_password()
        assert len(user.password) == WPUser.WP_PASSWORD_LENGTH


class TestWPConfig:

    @pytest.fixture()
    def wp_config(self):
        wordpress = WPSite(
            openshift_env=settings.OPENSHIFT_ENV,
            wp_site_url="http://localhost/folder",
            wp_site_title="My test")
        return WPConfig(wordpress)


class TestWPGenerator:

    TAGLINE_WITH_ACCENT = "démo"
    SAME_SCIPER_ID = 157489

    @pytest.fixture()
    def wp_generator(self):
        generator = MockedWPGenerator(
            {'openshift_env': settings.OPENSHIFT_ENV,
             'wp_site_url': "http://localhost/folder",
             'wp_site_title': 'DM',
             'wp_tagline': self.TAGLINE_WITH_ACCENT,
             'unit_name': 'idevelop',
             'langs': 'en',
             'updates_automatic': False})
        generator.clean()
        return generator

    def test_config(self, wp_generator):
        assert wp_generator.wp_config.installs_locked == settings.DEFAULT_CONFIG_INSTALLS_LOCKED
        assert wp_generator.wp_config.updates_automatic is False
