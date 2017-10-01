import pytest

from utils import Utils
from generator.generator import WPSite, WPUser, WPRawConfig, MockedWPGenerator


class TestWPSite:

    @pytest.fixture(scope='module')
    def wordpress(self):
        return WPSite(
            openshift_env="test",
            wp_site_url="https://localhost/folder",
            wp_default_site_title="My test")

    def test_path(self, wordpress):
        assert wordpress.path == "/srv/test/localhost/htdocs/folder"

    def test_url(self, wordpress):
        assert wordpress.url == "http://localhost/folder"


class TestWPUser:

    def test_password(self):
        user = WPUser("test", "test@example.com")
        assert user.password is None
        user.set_password()
        assert len(user.password) == WPUser.WP_PASSWORD_LENGTH

    def test_from_sciper(self):
        # TODO: mock LDAP calls get_username & get_email
        pass


class TestWPRawConfig:

    @pytest.fixture()
    def wp_config(self):
        wordpress = WPSite(
            openshift_env="test",
            wp_site_url="https://localhost/folder",
            wp_default_site_title="My test")
        return WPRawConfig(wordpress)

    def test_is_installed(self, wp_config):
        pass

    def test_is_install_valid(self, wp_config):
        pass

    def test_add_wp_user(self, wp_config):
        pass


class TestWPGenerator:

    @pytest.fixture()
    def wp_generator(self):
        return MockedWPGenerator(
            openshift_env=Utils.get_mandatory_env(key="WP_ENV"),
            wp_site_url="https://localhost/folder",
            wp_default_site_title="My test",
            owner_id="157489",
            responsible_id="157489")

    def test_prepare_db(self, wp_generator):
        pass

    def test_install_wp(self, wp_generator):
        pass

    def test_generate(self, wp_generator):
        pass
