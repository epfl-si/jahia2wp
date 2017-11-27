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
from epflldap.ldap_search import get_unit_id

from settings import OPENSHIFT_ENVS, SUPPORTED_LANGUAGES

from django.core.validators import RegexValidator
from django.conf import settings as dj_settings

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
    return RegexValidator(regex="^[0-9]+$")(text)


def validate_string(text):
    return RegexValidator(regex="^.+$")(text)


def validate_yes_or_no(text):
    return ChoiceValidator(choices=['yes', 'no'])(text)


def validate_gaspar_username(name):
    return RegexValidator(regex="^[_\-\.a-zA-Z0-9]+$")(name)


def validate_db_name(name):
    return RegexValidator(regex="^[a-z0-9]{8,16}$")(name)


def validate_openshift_env(text):
    return ChoiceValidator(OPENSHIFT_ENVS)(text)


def validate_site_type(text):
    return RegexValidator(regex="^wordpress$")(text)


def validate_theme(text):
    return RegexValidator(regex="^[a-zA-Z0-9_-]+$")(text)


def validate_theme_faculty(text):
    return RegexValidator(regex="^(|cdh|cdm|enac|ic|sb|sti|sv)$")(text)


def validate_languages(text):
    return MultipleChoicesValidator(SUPPORTED_LANGUAGES)(text)


def validate_backup_type(text):
    return ChoiceValidator(choices=['inc', 'full'])(text)

# FIXME: le validate doit seulement retourner une execption
# en cas de problème
def validate_unit(unit_name):
    try:
        unit_id = get_unit_id(unit_name)
    except:
        # Import here to avoid recursive import
        from wordpress import WPException

        raise WPException("The unit name {} doesn't exist".format(unit_name))
    return unit_id, unit_name
