""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Validators are extended from Django ones :
    https://docs.djangoproject.com/fr/1.11/ref/validators

    - RegexValidator
    - EmailValidator
    - URLValidator
    - DecimalValidator
    - validate_email
    - validate_slug
    - validate_unicode_slug
    - MaxValueValidator
    - MinValueValidator
    - MaxLengthValidator
    - MinLengthValidator

    They are functions (or callable objects) that raise a ValidationError on failure
"""
from django.core.validators import RegexValidator

from settings import OPENSHIFT_ENVS, SUPPORTED_LANGUAGES


class ChoiceValidator(RegexValidator):

    def __init__(self, choices, **kwargs):
        regex = "^({})$".format("|".join(choices))
        super(ChoiceValidator, self).__init__(regex=regex, **kwargs)


class MultipleChoicesValidator(RegexValidator):

    def __init__(self, choices, separator=',', **kwargs):
        base_regex = "({})".format("|".join(choices))
        regex = "^{0}(,{0})*$".format(base_regex)
        super(MultipleChoicesValidator, self).__init__(regex=regex, **kwargs)


def validate_integer(text):
    return RegexValidator(regex="^[0-9]+$")(text)


def validate_string(text):
    return RegexValidator(regex="^.+$")(text)


def validate_yes_or_no(text):
    return ChoiceValidator(choices=['yes', 'no'])(text)


def validate_gaspar_username(name):
    return RegexValidator(regex="^[_\-\.a-z0-9]+$")(name)


def validate_db_name(name):
    return RegexValidator(regex="^[a-z0-9]{8,16}$")(name)


def validate_openshift_env(text):
    return ChoiceValidator(OPENSHIFT_ENVS)(text)


def validate_site_type(text):
    return RegexValidator(regex="^wordpress$")(text)


def validate_theme(text):
    return RegexValidator(regex="^EPFL$")(text)


def validate_languages(text):
    return MultipleChoicesValidator(SUPPORTED_LANGUAGES)(text)
