"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os

VERSION = "0.2.2"

DATA_PATH = os.path.abspath(
    os.path.sep.join([
        os.path.dirname(__file__),
        '..',
        'data',
        'wp',
        ]
    )
)

WP_DIRS = ['wp-admin', 'wp-content', 'wp-includes']
WP_FILES = [
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
