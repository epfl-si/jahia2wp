import pytest
import yaml
import os
from importlib import reload
import settings
from wordpress import WPPluginList, WPPluginConfig, WPConfig, WPMuPluginConfig
from wordpress.generator import MockedWPGenerator

SITE_URL_GENERIC = "http://localhost/"
SITE_URL_SPECIFIC = "http://localhost/{}".format(settings.TEST_SITE)

"""
Load fake environment variables for every test
FIXME: Can be improved. Information can be found here :
- https://docs.pytest.org/en/2.7.3/xunit_setup.html
- http://agiletesting.blogspot.ch/2005/01/python-unit-testing-part-3-pytest-tool.html
"""
os.environ["PLUGINS_CONFIG_BASE_PATH"] = os.path.join(settings.SRC_DIR_PATH, "wordpress/tests/plugins")
reload(settings)

"""
If you want to execute pytest locally to your computer (= not on Travis), you have to :
$ make exec
$ vjahia
$ pytest -x wordpress/tests/test_plugins.py
"""


@pytest.fixture(scope="module")
def wp_plugin_list():
    return WPPluginList(
        settings.PLUGINS_CONFIG_GENERIC_FOLDER,
        'config-lot1.yml',
        settings.PLUGINS_CONFIG_SPECIFIC_FOLDER)


@pytest.fixture(scope="class")
def wp_generator_generic():
    # To generate website with generic plugin list/configuration
    generator = MockedWPGenerator(
                openshift_env=settings.OPENSHIFT_ENV,
                wp_site_url=SITE_URL_GENERIC,
                wp_default_site_title="My test")
    generator.clean()
    generator.generate()
    return generator


@pytest.fixture(scope="class")
def wp_generator_specific():
    # To generate website with specific plugin list/configuration
    generator = MockedWPGenerator(
                openshift_env=settings.OPENSHIFT_ENV,
                wp_site_url=SITE_URL_SPECIFIC,
                wp_default_site_title="My test")
    generator.clean()
    generator.generate()
    return generator


def test_yaml_include():
    # Generate filename to open regarding current script path
    yaml_path = os.path.join(os.path.dirname(os.path.realpath(__file__)), 'yaml-root.yml')
    yaml_content = yaml.load(open(yaml_path, 'r'))
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
        plugins_to_test = ['add-to-any', 'hello', 'redirection', 'akismet']

        plugin_list = wp_plugin_list.plugins(settings.TEST_SITE)
        assert len(plugin_list) == len(plugins_to_test)
        for plugin_name in plugins_to_test:
            assert plugin_name in plugin_list


class TestWPPluginConfig:

    def test_valid_install_generic(self, wp_generator_generic, wp_plugin_list):

        # Plugins and if they have to be installed or not
        for plugin_name, installed in {
            'add-to-any': True,
            'hello': False,
            'akismet': True
        }.items():

            plugin_config = wp_plugin_list.plugins()[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_generator_generic.wp_site, plugin_name, plugin_config)

            assert wp_plugin_config.is_installed is installed

    def test_valid_install_specific(self, wp_generator_specific, wp_plugin_list):

        # Plugins and if they have to be installed or not
        for plugin_name, installed in {
            'add-to-any': True,
            'redirection': True,
            'hello': False,
            'akismet': False
        }.items():

            plugin_config = wp_plugin_list.plugins(settings.TEST_SITE)[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_generator_specific.wp_site, plugin_name, plugin_config)

            assert wp_plugin_config.is_installed is installed

    def test_is_activated_generic(self, wp_generator_generic, wp_plugin_list):

        # plugins and if they have to be activated or not
        for plugin_name, activated in {
            'add-to-any': True,
            'akismet': False
        }.items():

            plugin_config = wp_plugin_list.plugins()[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_generator_generic.wp_site, plugin_name, plugin_config)

            assert wp_plugin_config.is_activated is activated

    def test_is_activated_specific(self, wp_generator_specific, wp_plugin_list):

        # plugins and if they have to be activated or not
        for plugin_name, activated in {
            'add-to-any': True,
            'redirection': True
        }.items():

            plugin_config = wp_plugin_list.plugins(settings.TEST_SITE)[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_generator_specific.wp_site, plugin_name, plugin_config)

            assert wp_plugin_config.is_activated is activated

    def test_mu_plugins_installed(self, wp_generator_specific):
        assert os.path.exists(WPMuPluginConfig(wp_generator_specific.wp_site, "epfl-functions.php").path)

    def test_valid_uninstall(self, wp_generator_specific, wp_plugin_list):

        for plugin_name in ['add-to-any', 'redirection']:

            plugin_config = wp_plugin_list.plugins(settings.TEST_SITE)[plugin_name]
            wp_plugin_config = WPPluginConfig(wp_generator_specific.wp_site, plugin_name, plugin_config)
            wp_plugin_config.uninstall()
            assert wp_plugin_config.is_installed is False


class TestWPPluginConfigRestore:

    def test_restore_generic_config(self, wp_generator_generic, wp_plugin_list):

        # First, uninstall from WP installation
        plugin_config = wp_plugin_list.plugins()['add-to-any']
        wp_plugin_config = WPPluginConfig(wp_generator_generic.wp_site, 'add-to-any', plugin_config)
        wp_plugin_config.uninstall()

        # Then, reinstall plugin and configure it
        wp_plugin_config.install()
        wp_plugin_config.configure()

        # Check plugin options
        wp_config = WPConfig(wp_generator_generic.wp_site)
        assert wp_config.run_wp_cli("option get addtoany_options") == 'test'

    def test_restore_specific_config(self, wp_generator_generic, wp_plugin_list):

        # First, uninstall from WP installation
        plugin_config = wp_plugin_list.plugins(settings.TEST_SITE)['add-to-any']
        wp_plugin_config = WPPluginConfig(wp_generator_generic.wp_site, 'add-to-any', plugin_config)
        wp_plugin_config.uninstall()

        # Then, reinstall and configure it
        wp_plugin_config.install()
        wp_plugin_config.configure()

        # Check plugin options
        wp_config = WPConfig(wp_generator_generic.wp_site)
        assert wp_config.run_wp_cli("option get addtoany_options") == 'test_overload'
        assert wp_config.run_wp_cli("option get addtoany_dummy") == 'dummy'


def test_teardown(wp_generator_generic, wp_generator_specific):
    wp_generator_generic.clean()
    wp_generator_specific.clean()
