"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os

from utils import Utils

VERSION = "0.2.15"

# This Docker IP address is used for automatic testing.
# Docker may change it in the future, which will cause some tests to fail.
DOCKER_IP = "172.17.0.1"

SRC_DIR_PATH = os.path.dirname(os.path.realpath(__file__))
DATA_PATH = os.path.abspath(
    os.path.sep.join([SRC_DIR_PATH, '..', 'data'])
)
WP_PATH = os.path.join(DATA_PATH, 'wp')
BACKUP_PATH = Utils.get_optional_env(
    "BACKUP_PATH", os.path.join(DATA_PATH, 'backups'))

ENV_DIRS = ['logs', 'venv', 'jahia2wp']

WP_DIRS = ['wp-admin', 'wp-content', 'wp-includes']
WP_FILES = [
    '.htaccess',
    'index.php',
    'license.txt',
    'readme.html',
    'wp-activate.php',
    'wp-admin',
    'wp-blog-header.php',
    'wp-comments-post.php',
    'wp-config-sample.php',
    'wp-config.php',
    'wp-content',
    'wp-cron.php',
    'wp-includes',
    'wp-links-opml.php',
    'wp-load.php',
    'wp-login.php',
    'wp-mail.php',
    'wp-settings.php',
    'wp-signup.php',
    'wp-trackback.php',
    'xmlrpc.php'
]

WP_CONFIG_KEYS = [
    'table_prefix',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
    'DB_HOST',
    'DB_CHARSET',
    'DB_COLLATE',
    'AUTH_KEY',
    'SECURE_AUTH_KEY',
    'LOGGED_IN_KEY',
    'NONCE_KEY',
    'AUTH_SALT',
    'SECURE_AUTH_SALT',
    'LOGGED_IN_SALT',
    'NONCE_SALT',
]

SUPPORTED_LANGUAGES = [
    "fr",
    "en",
    "de",
    "es",
    "ro",
    "gr",
    "it"
]

DEFAULT_CONFIG_INSTALLS_LOCKED = True
DEFAULT_CONFIG_UPDATES_AUTOMATIC = True

DEFAULT_THEME_NAME = 'epfl'

PLUGINS_CONFIG_BASE_PATH = Utils.get_optional_env(
    "PLUGINS_CONFIG_BASE_PATH", os.path.sep.join([SRC_DIR_PATH, '..', 'data', 'plugins']))
PLUGINS_CONFIG_GENERIC_FOLDER = os.path.join(PLUGINS_CONFIG_BASE_PATH, 'generic')
PLUGINS_CONFIG_SPECIFIC_FOLDER = os.path.join(PLUGINS_CONFIG_BASE_PATH, 'specific')

PLUGIN_SOURCE_WP_STORE = 'web'
PLUGIN_ACTION_INSTALL = 'install'
PLUGIN_ACTION_UNINSTALL = 'uninstall'
PLUGIN_ACTION_NOTHING = 'nothing'

""" Tables in which configuration is stored, with 'auto gen id' fields and 'unique field'
(others than only auto-gen field). Those tables must be sorted to satisfy foreign keys.
Those are the 'short names' of the tables. We will need to add WordPress table prefix to
have complete name. """
WP_PLUGIN_CONFIG_TABLES = {
    'postmeta': ['meta_id', None],
    'options': ['option_id', 'option_name'],
    'terms': ['term_id', None],
    'termmeta': ['meta_id', None],
    'term_taxonomy': ['term_taxonomy_id', None],
    'term_relationships': [None, ['object_id', 'term_taxonomy_id']],
}

""" Relation between configuration tables. There are no explicit relation between tables in DB but there are
relation coded in WP. """
WP_PLUGIN_TABLES_RELATIONS = {
    'termmeta': {'term_id': 'terms'},
    'term_taxonomy': {'term_id': 'terms'},
    'term_relationships': {'term_taxonomy_id': 'term_taxonomy'}
}

# What class to use for given plugin_name
WP_DEFAULT_PLUGIN_CONFIG = "wordpress.plugins.config.WPPluginConfig"
WP_PLUGIN_CONFIG_CLASS_BY_NAME = {
    "polylang": "wordpress.plugins.polylang.WPPolylangConfig",
}

JAHIA_USER = Utils.get_optional_env("JAHIA_USER", "admin")
JAHIA_HOST = Utils.get_optional_env("JAHIA_HOST", "localhost")
JAHIA_PROTOCOL = "http"
JAHIA_ZIP_PATH = Utils.get_optional_env("JAHIA_ZIP_PATH", ".")
