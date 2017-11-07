import pytest
from settings import *
from wordpress.plugins import *

def test_yaml_include():
    pass



@pytest.fixture(scope="module")
def wp_plugin_list():
    return WPPluginList(PLUGINS_CONFIG_GENERIC_FOLDER, 'config-lot1.yml', PLUGINS_CONFIG_SPECIFIC_FOLDER)

@pytest.fixture(scope="module")
def wp_site():
    return WPSite(
        openshift_env="test",
        wp_site_url="https://localhost/unittest",
        wp_default_site_title="My test")


class TestWPPluginList:

    def test_generic_list(self, wp_plugin_list):
        assert len(wp_plugin_list.plugins()) == 1

    def test_list_for_site(self, wp_plugin_list):
        assert len(wp_plugin_list.plugins('unittest')) == 2


class TestWPPluginConfigRestore:

    def test_restore_add_to_any_config(self):
        pass


class TestWPPluginConfig:

    def test_valid_install(self, wp_site, wp_plugin_list):

        # plugin_name = 'add-to-any'
        #
        # # Generic
        # plugin_config = wp_plugin_list.plugins()[plugin_name]
        # wp_plugin_config = WPPluginConfig(wp_site, plugin_name, plugin_config)
        #
        # assert wp_plugin_config.is_installed is False
        #
        # # Specific
        # plugin_config = wp_plugin_list.plugins('unittest')[plugin_name]
        # wp_plugin_config = WPPluginConfig(wp_site, plugin_name, plugin_config)
        #
        # assert wp_plugin_config.is_installed is False
        pass

    def test_valid_uninstall(self):
        pass

    def test_is_activated(self):
        pass


class TestWPPluginConfigExtractor:
    pass
