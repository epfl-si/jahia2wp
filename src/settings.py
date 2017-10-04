"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os

VERSION = "0.1.0"

DATA_PATH = os.path.abspath(
    os.path.sep.join([
        os.path.dirname(__file__),
        '..',
        'data',
        'wp',
        ]
    )
)

OPENSHIFT_ENVS = [
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
