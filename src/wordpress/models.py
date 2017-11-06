import os
import re
import logging

from urllib.parse import urlparse
from epflldap.ldap_search import get_username, get_email

from django.core.validators import URLValidator, EmailValidator, ValidationError
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
        if wp_default_site_title is not None:
            validate_string(wp_default_site_title)

        # extract domain and folder from given url
        url = urlparse(wp_site_url)

        self.openshift_env = openshift_env

        # set WP informations
        self.domain = url.netloc.strip('/')
        self.folder = url.path.strip('/')
        self.wp_default_site_title = wp_default_site_title or self.DEFAULT_TITLE

    def __repr__(self):
        return self.url

    @property
    def path(self):
        return "/srv/{0.openshift_env}/{0.domain}/htdocs/{0.folder}".format(self)

    @property
    def url(self):
        return "{0.PROTOCOL}://{0.domain}/{0.folder}".format(self)

    @property
    def name(self):
        # return domain site has no folder
        if not self.folder:
            return self.domain

        # returns last folder if site as a folder defined
        return self.folder.split('/')[-1]

    @classmethod
    def from_path(cls, openshift_env, path):
        given_path = os.path.abspath(path).rstrip('/')

        # validate given path
        if not os.path.isdir(given_path):
            logging.warning("given path '{}' is not a valid dir".format(given_path))

        # validate path within env
        env_path = '/srv/{}'.format(openshift_env)
        if not given_path.startswith(env_path):
            raise ValueError("given path '{}' should be included in given openshift_env '{}'".format(
                given_path, env_path
            ))

        # path is env
        if env_path == given_path:
            logging.debug("given path is openshift env: no site here")
            return None

        # build URL from path
        if 'htdocs' in given_path:
            # extract domain and folder(s)
            regex = re.compile("/([^/]*)")
            directories = regex.findall(os.path.abspath(path))
            htdocs_index = directories.index('htdocs')
            domain = directories[htdocs_index-1]
            folders = '/'.join(directories[htdocs_index+1:])
            url = "{}://{}/{}".format(cls.PROTOCOL, domain, folders)
        else:
            # domain only
            domain = os.path.basename(given_path)
            url = "{}://{}".format(cls.PROTOCOL, domain)

        # return WPSite
        return cls(openshift_env, url)


class WPUser:
    """ Pure python object that will define a WP user by its name and email.
        its password can be defined or generated
    """

    WP_PASSWORD_LENGTH = 32

    def __init__(self, username, email, password=None, display_name=None, role=None):
        # validate input
        EmailValidator()(email)
        validate_gaspar_username(username)

        self.username = username
        self.email = email
        self.password = password
        self.display_name = display_name or username
        self.role = role

    def __repr__(self):
        return "{0.username}:{0.email} <{0.role}>".format(self)

    @classmethod
    def from_sciper(cls, sciper_id, role='administrator'):
        try:
            return cls(
                username=get_username(sciper=sciper_id),
                email=get_email(sciper=sciper_id),
                role=role
            )
        except IndexError:
            raise WPException("could not get user details for sciper %s" % sciper_id)
        except ValidationError as err:
            raise WPException("username or email '%s': %s" % (err.params, err.code))

    def set_password(self, password=None):
        self.password = password or Utils.generate_password(self.WP_PASSWORD_LENGTH)
