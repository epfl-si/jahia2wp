import pytest

from settings import DEFAULT_CONFIG_INSTALLS_LOCKED
from utils import Utils
from wordpress import WPSite, WPUser, WPConfig
from wordpress.generator import MockedWPGenerator


class TestWPSite:

    @pytest.fixture(scope='module')
    def wordpress(self):
        return WPSite(
            openshift_env="test",
            wp_site_url="http://localhost/folder",
            wp_default_site_title="My test")

    def test_path(self, wordpress):
        assert wordpress.path == "/srv/test/localhost/htdocs/folder"

    def test_url(self, wordpress):
        assert wordpress.url == "http://localhost/folder"

    def test_name(self, wordpress):
        assert wordpress.name == "folder"
        assert WPSite(
            openshift_env="test",
            wp_site_url="http://localhost/") \
            .name == "localhost"
        assert WPSite(
            openshift_env="test",
            wp_site_url="http://www.epfl.ch/") \
            .name == "www"
        assert WPSite.from_path("test", "/srv/test/localhost/htdocs/folder") \
            .name == "folder"
        assert WPSite.from_path("test", "/srv/test/localhost/htdocs/folder/sub") \
            .name == "sub"

    def test_failing_url_from_path(self):
        with pytest.raises(ValueError):
            WPSite.from_path("ebreton", "/srv/test/localhost")

    def test_valid_url_from_path(self):
        assert WPSite.from_path("test", "/srv/test/localhost") \
            .url == "http://localhost/"
        assert WPSite.from_path("test", "/srv/test/localhost/htdocs/folder") \
            .url == "http://localhost/folder"
        assert WPSite.from_path("test", "/srv/test/localhost/htdocs/folder/sub") \
            .url == "http://localhost/folder/sub"


class TestWPUser:

    def test_password(self):
        user = WPUser("test", "test@example.com")
        assert user.password is None
        user.set_password()
        assert len(user.password) == WPUser.WP_PASSWORD_LENGTH

    def test_from_sciper(self):
        # TODO: mock LDAP calls get_username & get_email
        pass


class TestWPConfig:

    @pytest.fixture()
    def wp_config(self):
        wordpress = WPSite(
            openshift_env="test",
            wp_site_url="http://localhost/folder",
            wp_default_site_title="My test")
        return WPConfig(wordpress)

    def test_is_installed(self, wp_config):
        pass

    def test_is_install_valid(self, wp_config):
        pass

    def test_add_wp_user(self, wp_config):
        pass


class TestWPGenerator:

    TITLE_WITH_ACCENT = "démo"
    SAME_SCIPER_ID = 157489

    @pytest.fixture()
    def wp_generator(self):
        generator = MockedWPGenerator(
            openshift_env=Utils.get_mandatory_env(key="WP_ENV"),
            wp_site_url="http://localhost/folder",
            wp_default_site_title=self.TITLE_WITH_ACCENT,
            owner_id=self.SAME_SCIPER_ID,
            responsible_id=self.SAME_SCIPER_ID,
            updates_automatic=False)
        generator.clean()
        return generator

    def test_config(self, wp_generator):
        assert wp_generator.wp_config.installs_locked == DEFAULT_CONFIG_INSTALLS_LOCKED
        assert wp_generator.wp_config.updates_automatic is False

    def test_prepare_db(self, wp_generator):
        pass

    def test_install_wp(self, wp_generator):
        pass

    def test_generate(self, wp_generator):
        assert wp_generator.generate()
