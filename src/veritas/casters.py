""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""

from settings import SUPPORTED_TRUE_STRINGS


def cast_integer(text):
    return int(text)


def cast_yes_or_no(text):
    if type(text) is bool:
        return text
    return text.lower() == 'yes'


def cast_boolean(text):
    if type(text) is bool:
        return text
    return text.lower() in SUPPORTED_TRUE_STRINGS
