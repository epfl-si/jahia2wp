""" All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017
jahia2wp: an amazing tool !

Usage:
  jahia2wp.py helloworld

Options:
  -h --help                     Show this screen.
  -v --version                  Show version.
"""
from docopt import docopt
from settings import VERSION


def main(args):

    if args.get("helloworld"):
        print("Hello World")
        return True


if __name__ == '__main__':

    # docopt return a dictionary with all arguments
    # __doc__ contains package docstring
    args = docopt(__doc__, version=VERSION)

    main(args)
