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

class WXMLError(Exception):
    pass

class Ventilator:
    def __init__(self, file):
        self.etree = lxml.etree.parse(file)

    def ventilate(self, flags):
        return self.etree

def to_string(what):
    if (isinstance(what, lxml.etree._Element) or
        isinstance(what, lxml.etree._ElementTree)):
        return lxml.etree.tostring(what, encoding='utf-8').decode('utf-8')
    else:
        return str(what)

WP_NSMAP = {
    'content': 'http://purl.org/rss/1.0/modules/content/',
    'dc'     : 'http://purl.org/dc/elements/1.1/',
    'wp'     : 'http://wordpress.org/export/1.2/',
    'wfw'    : 'http://wellformedweb.org/CommentAPI/',
    'excerpt': 'http://wordpress.org/export/1.2/excerpt/'
}

class Item:
    """An <item> in the XML document.

    An item represents a WordPress post of any type, including attachments,
    Polylang translation tables and even weirder instances (but see
    the all_pages class method).
    """
    def __init__(self, etree_elt):
        self._elt = etree_elt

    @classmethod
    def all(cls, etree):
        return [cls(elt) for elt in etree.xpath("//item")]

    @classmethod
    def insert(cls, etree):
        """Create and return an empty <item> element."""
        new_elt = lxml.etree.Element("item")
        existing_items = etree.xpath("//item")
        if existing_items:
            existing_items[0].addprevious(new_elt)
        else:
            etree.xpath("/rss/channel").append(new_elt)
        return cls(new_elt)

    def delete(self):
        self._elt.getparent().remove(self._elt)
        self._elt = 'DELETED'

    def __id_node(self):
        nodes = self._elt.xpath("wp:post_id", namespaces=WP_NSMAP)
        if len(nodes) == 1:
            return nodes[0]
        elif len(nodes) == 0:
            new_post_id = lxml.etree.Element("{%s}post_id" % WP_NSMAP["wp"])
            self._elt.append(new_post_id)
            return new_post_id
        else:
            raise WXMLError("%d wp:post_id's in item!" % len(nodes))

    @property
    def id(self):
        id_text = self.__id_node().text
        return None if id_text is None else int(id_text)

    @id.setter
    def id(self, new_id):
        self.__id_node().text = str(new_id)

    def __repr__(self):
        return '[Item %s]' % to_string(self._elt)

def demo():
    """For running in ipython - Not used in production"""
    import subprocess
    global srcdir, v, i
    srcdir = (subprocess.run("git rev-parse --show-toplevel", stdout=subprocess.PIPE, shell=True).stdout)[:-1]
    v = Ventilator("%s/wxr/cri.xml" % srcdir.decode("utf-8"))
    i = Item.insert(v.etree)
    print(repr(i.id))
    i.id = 5
    print(repr(i.id))
    print(to_string(i))

if __name__ == '__main__':
    args = docopt(__doc__)
    v = Ventilator(args["<input_xml_file>"])
    print(to_string(v.ventilate(args)))
