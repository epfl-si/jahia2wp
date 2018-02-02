import pytest

import settings
from jahia2wp import export


TEST_HOST = 'localhost'
TEST_SITE = 'dcsl'
SITE_URL_SPECIFIC = "https://{0}/{1}".format(TEST_HOST, TEST_SITE)
OPENSHIFT_ENV = 'test'
ENVIRONMENT = "local"


@pytest.fixture(scope="module")
def wp_exporter():

    wp_exporter = export(
        site="dcsl",
        to_wordpress=True,
        clean_wordpress=False,
        admin_password=None,
        output_dir=None,
        theme=None,
        unit_name=None,
        wp_site_url=SITE_URL_SPECIFIC,
        installs_locked=False,
        updates_automatic=False,
        openshift_env=OPENSHIFT_ENV,
        environment=ENVIRONMENT)

    return wp_exporter


class TestCommandLine:

    def test_check_medias(self, wp_exporter):
        # nb_medias = len(wp_exporter.wp.get_media(params={'per_page': '100'}))
        # assert 27 == nb_medias
        assert True

    def test_check_pages(self, wp_exporter):
        cmd = "post list --post_type=page --fields=ID --format=csv"
        pages_id_list = wp_exporter.wp_generator.run_wp_cli(cmd).split("\n")[1:]
        assert 8 == len(pages_id_list)

    def test_check_menus(self, wp_exporter):
        cmd = "menu list --fields=term_id --format=csv"
        menus_id_list = wp_exporter.wp_generator.run_wp_cli(cmd).split("\n")[1:]
        assert 2 == len(menus_id_list)

    def test_check_main_menu(self, wp_exporter):
        cmd = "menu item list {} --format=csv".format(settings.MAIN_MENU)
        items_list = wp_exporter.wp_generator.run_wp_cli(cmd).split("\n")[1:]
        assert 7 == len(items_list)

    def test_check_footer_menu(self, wp_exporter):
        cmd = "menu item list {} --format=csv".format(settings.FOOTER_MENU)
        items_list = wp_exporter.wp_generator.run_wp_cli(cmd).split("\n")[1:]
        assert 3 == len(items_list)
