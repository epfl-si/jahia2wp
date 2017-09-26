"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
from utils import Utils


def test_generate_password():
    password = Utils.generate_password(32)
    assert 32 == len(password)
