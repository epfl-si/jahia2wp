import pytest
import yaml
from settings import *
from wordpress.plugins import *
from wordpress.generator import *

TEST_SITE='unittest'
SITE_URL_GENERIC="https://localhost/"
SITE_URL_SPECIFIC="https://localhost/{}".format(TEST_SITE)
TEST_ENV = 'test'

@pytest.fixture(scope="module")
def wp_plugin_list():
    return WPPluginList(PLUGINS_CONFIG_GENERIC_FOLDER, 'config-lot1.yml', PLUGINS_CONFIG_SPECIFIC_FOLDER)

@pytest.fixture(scope="class")
def wp_site_generic():
    # To generate website with generic plugin list/configuration
    generator = MockedWPGenerator(
                openshift_env=TEST_ENV,
                wp_site_url=SITE_URL_GENERIC,
                wp_default_site_title="My test")
    generator.clean()
    generator.generate()
    return generator.wp_site

@pytest.fixture(scope="class")
def wp_site_specific():
    # To generate website with specific plugin list/configuration
    generator = MockedWPGenerator(
                openshift_env=TEST_ENV,
                wp_site_url=SITE_URL_SPECIFIC,
                wp_default_site_title="My test")
    generator.clean()
    generator.generate()
    return generator.wp_site

def test_yaml_include():
    # Generate filename to open regarding current script path
    yaml_file = local_file = os.path.join(os.path.dirname(os.path.realpath(__file__)), 'yaml-root.yml')
    yaml_content = yaml.load(open(yaml_file, 'r'))
    assert yaml_content['root_value'] == 'root'
    assert yaml_content['included_value'] == 'included'


class TestWPPluginList:

    def test_generic_plugin_list(self, wp_plugin_list):
        plugins_to_test = ['add-to-any', 'hello', 'akismet']

        plugin_list = wp_plugin_list.plugins()
        assert len(plugin_list) == len(plugins_to_test)
        for plugin_name in plugins_to_test:
            assert plugin_name in plugin_list

    def test_specific_plugin_list(self, wp_plugin_list):
        plugins_to_test = ['add-to-any', 'hello', 'epfl_infoscience', 'akismet']

        plugin_list = wp_plugin_list.plugins(TEST_SITE)
        assert len(plugin_list) == len(plugins_to_test)
        for plugin_name in plugins_to_test:
            assert plugin_name in plugin_list


class TestWPPluginConfig:

    def test_valid_install_generic(self, wp_site_generic, wp_plugin_list):
        # Plugins and if they have to be installed or not
        plugins_to_test = {'add-to-any': True, 'hello': False, 'akismet': True}

        for plugin_name in plugins_to_test:

            plugin_config = wp_plugin_list.plugins()[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_site_generic, plugin_name, plugin_config)

            assert wp_plugin_config.is_installed is plugins_to_test[plugin_name]

    def test_valid_install_specific(self, wp_site_specific, wp_plugin_list):
        # Plugins and if they have to be installed or not
        plugins_to_test = {'add-to-any': True, 'epfl_infoscience': True, 'hello': False, 'akismet': False}

        for plugin_name in plugins_to_test:

            plugin_config = wp_plugin_list.plugins(TEST_SITE)[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_site_specific, plugin_name, plugin_config)

            assert wp_plugin_config.is_installed is plugins_to_test[plugin_name]

    def test_is_activated_generic(self, wp_site_generic, wp_plugin_list):
        # plugins and if they have to be activated or not
        plugins_to_test = {'add-to-any': True, 'akismet': False}

        for plugin_name in plugins_to_test:

            plugin_config = wp_plugin_list.plugins()[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_site_generic, plugin_name, plugin_config)

            assert wp_plugin_config.is_activated is plugins_to_test[plugin_name]

    def test_is_activated_specific(self, wp_site_specific, wp_plugin_list):
        # plugins and if they have to be activated or not
        plugins_to_test = {'add-to-any': True, 'epfl_infoscience': True}

        for plugin_name in plugins_to_test:

            plugin_config = wp_plugin_list.plugins(TEST_SITE)[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_site_specific, plugin_name, plugin_config)

            assert wp_plugin_config.is_activated is plugins_to_test[plugin_name]

    def test_valid_uninstall(self, wp_site_specific, wp_plugin_list):
        plugins_to_test = ['add-to-any', 'epfl_infoscience']

        for plugin_name in plugins_to_test:

            plugin_config = wp_plugin_list.plugins(TEST_SITE)[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_site_specific, plugin_name, plugin_config)
            wp_plugin_config.uninstall()
            assert wp_plugin_config.is_installed is False


class TestWPPluginConfigRestore:

    def test_restore_generic_config(self, wp_site_generic, wp_plugin_list):

        # First, uninstall from WP installation
        plugin_config = wp_plugin_list.plugins()['add-to-any']
        wp_plugin_config = WPPluginConfig(wp_site_generic, 'add-to-any', plugin_config)
        wp_plugin_config.uninstall()

        # Then, reinstall plugin and configure it
        wp_plugin_config.install()
        wp_plugin_config.configure()

        # Check plugin options
        wp_config = WPConfig(wp_site_generic)
        assert wp_config.run_wp_cli("option get addtoany_options") == 'test'

    def test_restore_specific_config(self, wp_site_generic, wp_plugin_list):

        # First, uninstall from WP installation
        plugin_config = wp_plugin_list.plugins(TEST_SITE)['add-to-any']
        wp_plugin_config = WPPluginConfig(wp_site_generic, 'add-to-any', plugin_config)
        wp_plugin_config.uninstall()

        # Then, reinstall and configure it
        wp_plugin_config.install()
        wp_plugin_config.configure()

        # Check plugin options
        wp_config = WPConfig(wp_site_generic)
        assert wp_config.run_wp_cli("option get addtoany_options") == 'test_overload'
        assert wp_config.run_wp_cli("option get addtoany_dummy") == 'dummy'

class TestWPPluginConfigExtractor:
    pass
