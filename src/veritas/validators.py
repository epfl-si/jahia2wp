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
import os
from django.conf import settings as dj_settings
from django.core.exceptions import ValidationError
from django.core.validators import RegexValidator
from epflldap.ldap_search import get_unit_id

from settings import SUPPORTED_LANGUAGES

dj_settings.configure(USE_I18N=False)


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
    if not type(text) is int:
        RegexValidator(regex="^[0-9]+$")(text)


def validate_string(text):
    RegexValidator(regex="^.+$")(text)


def validate_yes_or_no(text):
    if not type(text) is bool:
        ChoiceValidator(choices=['yes', 'no'])(text.lower())


def validate_gaspar_username(name):
    RegexValidator(regex="^[_\-\.a-zA-Z0-9]+$")(name)


def validate_db_name(name):
    RegexValidator(regex="^[a-z0-9]{8,16}$")(name)


def validate_openshift_env(text):
    if not os.path.isdir('/srv/{}'.format(text)):
        raise ValidationError("Openshift environment not valid")


def validate_site_type(text):
    RegexValidator(regex="^wordpress$")(text)


def validate_theme(text):
    RegexValidator(regex="^[a-zA-Z0-9_-]+$")(text)


def validate_theme_faculty(text):
    RegexValidator(regex="^(|cdh|cdm|enac|ic|sb|sti|sv|assoc)$")(text.lower())


def validate_languages(text):
    MultipleChoicesValidator(SUPPORTED_LANGUAGES)(text)


def validate_unit(unit_name):
    # FIXME: epfl-ldap should a LDAP Exception
    try:
        if unit_name:
            get_unit_id(unit_name)
    except Exception:
        raise ValidationError("The unit name {} doesn't exist".format(unit_name))


def mock_validate_unit(unit_name):
    pass
