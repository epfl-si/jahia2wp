from urllib.parse import urlparse
from epflldap.ldap_search import get_username, get_email

from django.core.validators import URLValidator, EmailValidator
from veritas.validators import validate_string, validate_openshift_env, validate_gaspar_username

from utils import Utils


class WPException(Exception):
    """ Simple Wrapper to customize a bit our Excepions """
    pass


class WPSite:
    """ Pure python object that will define a WP site by its path & url
        its title is optionnal, just to provide a default value to the final user
    """

    PROTOCOL = "http"
    DEFAULT_TITLE = "New WordPress"
    WP_VERSION = Utils.get_mandatory_env(key="WP_VERSION")

    def __init__(self, openshift_env, wp_site_url, wp_default_site_title=None):
        # validate input
        validate_openshift_env(openshift_env)
        URLValidator()(wp_site_url)
        if wp_default_site_title is None:
            validate_string(wp_default_site_title)

        # extract domain and folder from given url
        url = urlparse(wp_site_url)

        self.openshift_env = openshift_env

        # set WP informations
        self.domain = url.netloc.strip('/')
        self.folder = url.path.strip('/')
        self.wp_default_site_title = wp_default_site_title or self.DEFAULT_TITLE

    def __repr__(self):
        return "WP@{}/{}/{}".format(self.openshift_env, self.domain, self.folder)

    @property
    def path(self):
        return "/srv/{0.openshift_env}/{0.domain}/htdocs/{0.folder}".format(self)

    @property
    def url(self):
        return "{0.PROTOCOL}://{0.domain}/{0.folder}".format(self)


class WPUser:
    """ Pure python object that will define a WP user by its name and email.
        its password can be defined or generated
    """

    WP_PASSWORD_LENGTH = 32

    def __init__(self, username, email, password=None):
        # validate input
        EmailValidator()(email)
        validate_gaspar_username(username)

        self.username = username
        self.email = email
        self.password = password

    def __repr__(self):
        password_string = 'xxxx' if self.password is not None else 'None'
        return "{0.username}:{0.email} <{1}>".format(self, password_string)

    @classmethod
    def from_sciper(cls, sciper_id):
        try:
            return cls(
                username=get_username(sciper=sciper_id),
                email=get_email(sciper=sciper_id)
            )
        except IndexError:
            raise WPException("WPUser.from_sciper - %s - could not get user details", sciper_id)

    def set_password(self, password=None):
        self.password = password or Utils.generate_password(self.WP_PASSWORD_LENGTH)
