import os
import re
import logging

from urllib.parse import urlparse
from epflldap.ldap_search import get_username, get_email

from django.core.validators import ValidationError
from veritas.validators import validate_string, validate_openshift_env, validate_gaspar_username

from utils import Utils


class WPException(Exception):
    """ Simple Wrapper to customize a bit our Excepions """
    pass


class WPSite:
    """ Pure python object that will define a WP site by its path & url
        its title is optionnal, just to provide a default value to the final user
    """

    PROTOCOL = "https"
    DEFAULT_TITLE = ""
    DEFAULT_TAGLINE = "EPFL"
    WP_VERSION = Utils.get_mandatory_env(key="WP_VERSION")

    def __init__(self, openshift_env, wp_site_url, wp_site_title=None, wp_tagline=None):
        """
        Class constructor
        :param openshift_env: name of openshift environement
        :param wp_site_url: WordPress website URL
        :param wp_site_title: WordPress website title (same for all languages)
        :param wp_tagline: Dict with langs as key and tagline as value
        """
        # validate & transform args
        self.openshift_env = openshift_env.lower()
        url = urlparse(wp_site_url.lower())

        validate_openshift_env(self.openshift_env)
        if wp_site_title is not None:
            validate_string(wp_site_title)

        # set WP information
        self.domain = url.netloc.strip('/')
        self.folder = url.path.strip('/')
        self.wp_site_title = wp_site_title or self.DEFAULT_TITLE
        # If parameter not given
        if not wp_tagline:
            self.wp_tagline = self.DEFAULT_TAGLINE
        # Parameter given (dict)
        else:
            self.wp_tagline = wp_tagline
            # We check given information to be sure that we don't have a 'None' given
            for lang, wp_tagline in self.wp_tagline.items():
                if not wp_tagline:
                    self.wp_tagline[lang] = self.DEFAULT_TAGLINE
                validate_string(self.wp_tagline[lang])

    def __repr__(self):
        return self.url

    @property
    def path(self):
        if not self.folder:
            # specific case in order to avoid a trailing '/'
            return "/srv/{0.openshift_env}/{0.domain}/htdocs".format(self)
        else:
            return "/srv/{0.openshift_env}/{0.domain}/htdocs/{0.folder}".format(self)

    @property
    def url(self):
        """
        Returns WP site URL. URL is always returned without a / at the end.
        """
        # First, we generate only with hostname
        result = "{0.PROTOCOL}://{0.domain}".format(self)
        # If there a subfolder, we add it
        if self.folder != "":
            result = "{}/{}".format(result, self.folder)
        return result

    @property
    def name(self):
        # return domain site has no folder
        if not self.folder:
            return self.domain.split('.')[0]

        # returns last folder if site as a folder defined
        return self.folder.split('/')[-1]

    @classmethod
    def from_path(cls, path):
        given_path = os.path.abspath(path).rstrip('/')

        # extract openshift env
        env_match = re.match("/srv/([^/]+)", given_path)
        if env_match is None or not env_match.groups():
            raise ValueError("given path '{}' should be included in a valid openshift_env".format(given_path))
        openshift_env = env_match.groups()[0]

        # validate given path
        if not os.path.isdir(given_path):
            logging.warning("given path '%s' is not a valid dir", given_path)

        # make sure we are in an apache root directory
        if 'htdocs' not in given_path:
            return None

        # build URL from path
        regex = re.compile("/([^/]*)")
        directories = regex.findall(os.path.abspath(path))
        htdocs_index = directories.index('htdocs')
        domain = directories[htdocs_index-1]
        folders = '/'.join(directories[htdocs_index+1:])
        url = "{}://{}/{}".format(cls.PROTOCOL, domain, folders)

        # return WPSite
        return cls(openshift_env, url)


class WPUser:
    """ Pure python object that will define a WP user by its name and email.
        its password can be defined or generated
    """

    WP_PASSWORD_LENGTH = 32

    def __init__(self, username, email, password=None, display_name=None, role=None):
        # validate input
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
            raise WPException("could not get user details for sciper {}".format(sciper_id))
        except ValidationError as err:
            raise WPException("username or email '{}': {}".format(err.params, err.code))

    def set_password(self, password=None):
        self.password = password or Utils.generate_password(self.WP_PASSWORD_LENGTH)
