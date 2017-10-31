"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os

from utils import Utils

VERSION = "0.2.6"

DATA_PATH = os.path.abspath(
    os.path.sep.join([
        os.path.dirname(__file__),
        '..',
        'data',
        'wp',
        ]
    )
)

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

OPENSHIFT_ENVS = [
    # for testing purpose
    "your-env",
    "test",
    # real ones
    "dev",
    "int",
    "ebreton",
    "ejaep",
    "lvenries",
    "lboatto",
    "gcharmier",
    "lchaboudez"
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
current_file_path = os.path.dirname(os.path.realpath(__file__))
PLUGINS_CONFIG_GENERIC_FOLDER = os.path.sep.join([current_file_path, '..', 'data', 'plugins', 'generic'])
PLUGINS_CONFIG_SPECIFIC_FOLDER = os.path.sep.join([current_file_path, '..', 'data', 'plugins', 'specific'])
PLUGIN_SOURCE_WP_STORE = 'web'


JAHIA_USER = Utils.get_optional_env("JAHIA_USER", "admin")
JAHIA_HOST = Utils.get_optional_env("JAHIA_HOST", "localhost")
JAHIA_PROTOCOL = "http"
JAHIA_ZIP_PATH = Utils.get_optional_env("JAHIA_ZIP_PATH", ".")
