#!/usr/bin/env python3
# -*- coding: utf-8; -*-
# All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

"""wxr-ventilate: Transform WXR to WXR for the EPFL web sites

WXR, short for Wordpress Export RSS (https://github.com/pbiron/wxr) is
the native XML dump/restore format for WordPress.

This script consumes and produces WXR files. It takes part in the
"ventilation" process by filtering and reparenting pages so as to
make a number of independent WordPress instances appear to the public
as one large site, such as www.epfl.ch and vpsi.epfl.ch.

Usage:
  wxr-ventilate.py [options] <input_xml_file>

Options:
   --filter
   --add-parents <relativeuri>

"""
from docopt import docopt
import lxml.etree


class Ventilator:
    def __init__(self, file):
        self.etree = lxml.etree.parse(file)

    def ventilate(self):
        return self.etree

def to_string(what):
    if isinstance(what, lxml.etree._ElementTree):
        return lxml.etree.tostring(what, encoding='utf-8').decode('utf-8')
    else:
        return str(what)


if __name__ == '__main__':
    args = docopt(__doc__)
    v = Ventilator(args["<input_xml_file>"])
    print(to_string(v.ventilate()))
