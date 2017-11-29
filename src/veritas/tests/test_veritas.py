""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017 """
import os

from veritas.veritas import VeritasValidor, MOCK_JAHIA2WP_COLUMNS

CURRENT_DIR = os.path.dirname(__file__)
TEST_FILE = 'test_veritas_data.csv'
VALID_LINE = {
        'category': 'GeneralPublic',
        'comment': 'je mets ici',
        'installs_locked': 'yes',
        'langs': 'fr,en',
        'openshift_env': 'test',
        'owner_id': '123456',
        'responsible_id': '123456',
        'site_type': 'WordPress',
        'status': 'asked',
        'theme': 'epfl',
        'theme_faculty': '',
        'unit_name': 'VPR',
        'updates_automatic': 'no',
        'wp_default_site_title': 'Recherche',
        'wp_site_url': 'htt://www.epfl.ch/recherche'}


def test_validate():
    filename = os.path.join(CURRENT_DIR, TEST_FILE)

    validator = VeritasValidor(filename, columns=MOCK_JAHIA2WP_COLUMNS)

    # make sure test environment exists
    if not os.path.exists("/srv/test"):
        os.mkdir("/srv/test")

    validator.validate()

    errors = validator.errors

    assert "invalid wp_site_url" in errors[0].message
    assert "invalid site_type" in errors[1].message
    assert "wp_site_url is not unique" in errors[2].message
    assert "wp_site_url is not unique" in errors[3].message
    assert "invalid langs" in errors[4].message
    assert "invalid owner_id" in errors[5].message
    assert "invalid updates_automatic" in errors[6].message
    assert "wp_site_url is not unique" in errors[7].message
    assert "invalid openshift_env" in errors[8].message
    assert "wp_site_url is not unique" in errors[9].message


def test_get_valid_rows():
    filename = os.path.join(CURRENT_DIR, TEST_FILE)
    valid_lines = ((0, VALID_LINE),)
    validator = VeritasValidor(filename, columns=MOCK_JAHIA2WP_COLUMNS)
    assert validator.get_valid_rows() == valid_lines


def test_filter_method():
    filename = os.path.join(CURRENT_DIR, TEST_FILE)
    valid_lines = ((0, VALID_LINE),)
    assert valid_lines == VeritasValidor.filter_valid_rows(filename, columns=MOCK_JAHIA2WP_COLUMNS)
