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
            wp_default_site_title="My test")

    def test_path(self, wordpress):
        assert wordpress.path == ROOT_PATH + "htdocs/folder"

    def test_url(self, wordpress):
        assert wordpress.url == "http://localhost/folder"

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
        assert WPSite.from_path(settings.OPENSHIFT_ENV, ROOT_PATH + "htdocs/folder") \
            .name == "folder"
        assert WPSite.from_path(settings.OPENSHIFT_ENV, ROOT_PATH + "htdocs/folder/sub") \
            .name == "sub"

    def test_failing_url_from_path(self):
        with pytest.raises(ValueError):
            WPSite.from_path("idontexistandneverwill", ROOT_PATH)

    def test_valid_url_from_path(self):
        assert WPSite.from_path(settings.OPENSHIFT_ENV, ROOT_PATH) \
            .url == "http://localhost/"
        assert WPSite.from_path(settings.OPENSHIFT_ENV, ROOT_PATH + "htdocs/folder") \
            .url == "http://localhost/folder"
        assert WPSite.from_path(settings.OPENSHIFT_ENV, ROOT_PATH + "htdocs/folder/sub") \
            .url == "http://localhost/folder/sub"


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
            wp_default_site_title="My test")
        return WPConfig(wordpress)


class TestWPGenerator:

    TITLE_WITH_ACCENT = "démo"
    SAME_SCIPER_ID = 157489

    @pytest.fixture()
    def wp_generator(self):
        generator = MockedWPGenerator(
            settings.OPENSHIFT_ENV,
            "http://localhost/folder",
            wp_default_site_title=self.TITLE_WITH_ACCENT,
            unit_name="idevelop",
            updates_automatic=False)
        generator.clean()
        return generator

    def test_config(self, wp_generator):
        assert wp_generator.wp_config.installs_locked == settings.DEFAULT_CONFIG_INSTALLS_LOCKED
        assert wp_generator.wp_config.updates_automatic is False
