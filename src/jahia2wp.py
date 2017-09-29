""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
jahia2wp: an amazing tool !

Usage:
  jahia2wp.py veritas <path>

Options:
  -h --help                     Show this screen.
  -v --version                  Show version.
"""
from docopt import docopt
from settings import VERSION
from veritas.veritas import VeritasValidor


def main(args):

    # veritas
    if args.get("veritas"):
        validator = VeritasValidor(args["<path>"])

        validator.validate()

        validator.print_errors()

        return True


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    main(args)
