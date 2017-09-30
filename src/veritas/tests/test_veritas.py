""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017 """
import os

from veritas.veritas import VeritasValidor

CURRENT_DIR = os.path.dirname(__file__)
TEST_FILE = 'test_veritas_data.csv'


def test_validate():
    filename = os.path.join(CURRENT_DIR, TEST_FILE)
    validator = VeritasValidor(filename)

    validator.validate()

    errors = validator.errors

    assert "invalid wp_site_url" in errors[0].message
    assert "invalid site_type" in errors[1].message
    assert "url is not unique" in errors[2].message
    assert "invalid langs" in errors[3].message
    assert "invalid owner_id" in errors[4].message
    assert "invalid updates_automatic" in errors[5].message
    assert "invalid openshift_env" in errors[6].message
