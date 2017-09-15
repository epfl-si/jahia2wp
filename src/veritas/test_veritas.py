""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017 """

from .veritas import VeritasValidor


def test_validate():
    validator = VeritasValidor('./test_veritas_data.csv')

    validator.validate()

    errors = validator.errors

    assert "invalid admin" in errors[0]
    assert "invalid db name" in errors[1]
    assert "db name is not unique" in errors[2]
    assert "url is not unique" in errors[3]
    assert "invalid url" in errors[4]
    assert "invalid admin" in errors[5]
    assert "invalid db name" in errors[6]
    assert "invalid url" in errors[7]
