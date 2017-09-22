""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017 """

from veritas.veritas import VeritasValidor


def test_validate():
    validator = VeritasValidor('src/veritas/tests/test_veritas_data.csv')

    validator.validate()

    errors = validator.errors

    assert "invalid admin" in errors[0].message
    assert "invalid db name" in errors[1].message
    assert "url is not unique" in errors[2].message
    assert "db name is not unique" in errors[3].message
    assert "invalid url" in errors[4].message
    assert "invalid url" in errors[5].message
    assert "invalid admin" in errors[6].message
    assert "invalid db name" in errors[7].message
