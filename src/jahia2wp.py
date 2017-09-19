""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
jahia2wp: an amazing tool !

Usage:
  jahia2wp.py generate <csv_file> [--output-dir=<OUTPUT_DIR>] [--debug | --quiet]

Options:
  -h --help                     Show this screen.
  -v --version                  Show version.
"""

import logging

from docopt import docopt
from docopt_dispatch import dispatch

from generator.generator import Generator
from settings import VERSION
from utils import Utils


@dispatch.on('generate')
def generate(csv_file, **kwargs):
    Generator.run(csv_file)


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    # set logging config before anything else
    Utils.set_logging_config(args)

    logging.debug(args)

    dispatch(__doc__)
