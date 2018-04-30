import os

import pytest

from parser.jahia_site import Site
from parser.test import Data
from settings import DATA_PATH


def get_sites():
    """
    Return the list of jahia sites for which we have data
    """
    return ['atelierweb2']


@pytest.fixture(scope='module', params=get_sites())
def site(request):
    """
    Load site only once
    """
    site_name = request.param

    site_data_path = os.path.join(DATA_PATH, "jahia", request.param)

    return Site(site_data_path, site_name)


@pytest.fixture()
def data(site):
    """
    return the data of one jahia site
    """
    dictionary_name = site.name + "_data"
    return Data.__getattribute__(Data, dictionary_name)


class TestSiteProperties:
    """
      Check main properties of 'site' website
    """

    def test_name(self, site, data):
        assert site.name == data['properties']['name']

    def test_server_name(self, site, data):
        assert site.server_name == data['properties']['server_name']

    # FIXME : file paths are saved with the value of 'output-dir' at parsing time
    #  which differs from the one at testing time. The pathes should be relative...
    # def test_export_files(self, site, data):
    #     assert site.export_files == data['properties']['export_files']

    def test_languages(self, site, data):
        assert site.languages.sort() == data['properties']['languages'].sort()

    def test_title(self, site, data):
        assert site.title == data['properties']['title']

    def test_acronym(self, site, data):
        assert site.acronym == data['properties']['acronym']

    def test_theme(self, site, data):
        assert site.theme == data['properties']['theme']

    def test_css_url(self, site, data):
        assert site.css_url == data['properties']['css_url']

    def test_breadcrumb_title(self, site, data):
        assert site.breadcrumb_title == data['properties']['breadcrumb_title']

    def test_breadcrumb_url(self, site, data):
        assert site.breadcrumb_url == data['properties']['breadcrumb_url']

    def test_footers(self, site, data):
        footers = {}
        for language, links in site.footer.items():
            footers[language] = set([str(link) for link in links])
        assert footers == data['properties']['footers']

    def test_homepage__pid(self, site, data):
        assert site.homepage.pid == data['properties']['homepage__pid']


class TestSiteReport:

    def test_num_files(self, site, data):
        assert site.num_files == data['properties']['report']['num_files']

    def test_num_pages(self, site, data):
        assert site.num_pages == data['properties']['report']['num_pages']

    def test_internal_links(self, site, data):
        assert site.internal_links == data['properties']['report']['internal_links']

    def test_absolute_links(self, site, data):
        assert site.absolute_links == data['properties']['report']['absolute_links']

    def test_external_links(self, site, data):
        assert site.external_links == data['properties']['report']['external_links']

    def test_file_links(self, site, data):
        assert site.file_links == data['properties']['report']['file_links']

    def test_data_links(self, site, data):
        assert site.data_links == data['properties']['report']['data_links']

    def test_mailto_links(self, site, data):
        assert site.mailto_links == data['properties']['report']['mailto_links']

    def test_anchor_links(self, site, data):
        assert site.anchor_links == data['properties']['report']['anchor_links']

    def test_broken_links(self, site, data):
        assert site.broken_links == data['properties']['report']['broken_links']

    def test_unknown_links(self, site, data):
        assert site.unknown_links == data['properties']['report']['unknown_links']


class TestSiteStructure:
    """
      Check main elements of 'site' website
    """

    def test_files__len(self, site, data):
        assert len(site.files) == data['properties']['files__len']

    def test_nb_pages(self, site, data):
        assert len(site.pages_by_pid) == len(data['pages_by_pid'])

    def test_page_ids(self, site, data):
        assert set(site.pages_by_pid.keys()) == data['properties']['pages__ids']


class TestPages:
    """
      Check content of sidebar
    """

    def test_page_properties(self, site, data):

        for pid, page in site.pages_by_pid.items():
            expected_page = data['pages_by_pid'][pid]

            assert page.uuid == expected_page['uuid']
            assert page.template == expected_page['template']
            assert page.level == expected_page['level']
            assert len(page.children) == expected_page['children__len']
            assert set(page.contents.keys()) == expected_page['contents__keys']

    def test_page_content_properties(self, site, data):

        for pid, page in site.pages_by_pid.items():

            for language, content in page.contents.items():

                expected_content = data['pages_by_pid'][pid]['contents'][language]
                assert content.language == expected_content['language']
                assert content.path == expected_content['path']
                assert content.title == expected_content['title']
                assert content.menu_title == expected_content['menu_title']
                assert content.last_update == expected_content['last_update']

    def test_boxes(self, site, data):

        for pid, page in site.pages_by_pid.items():

            for language, page_content in page.contents.items():

                # create shortcuts for sidebar boxes
                boxes = page_content.sidebar.boxes
                expected_boxes = data['pages_by_pid'][pid]['contents'][language]['sidebar__boxes']

                # Nb boxes
                assert len(boxes) == len(expected_boxes)

                # Box title
                titles = [box.title for box in boxes]
                expected_titles = [data_box['title'] for data_box in expected_boxes]
                assert titles == expected_titles

                # Box content
                for index, box in enumerate(boxes):
                    expected_content = expected_boxes[index]['content__start']
                    assert box.content.startswith(expected_content)

                # Box type
                box_type = [box.type for box in boxes]
                expected_type = [data_box['type'] for data_box in expected_boxes]
                assert box_type == expected_type
