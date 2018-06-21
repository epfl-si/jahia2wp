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

class XmlElementProperty:
    """A property for XML sub-element getters/setters."""
    def __init__(self, ns_short, element_name, type=str):
        """Returns: An object with __get__ and __set__ methods that does the
        combined work of @property / @xx.setter for a datum of type
        `type' materialized as an XML child element
        `ns_short:element_name'
        """
        self._ns_short = ns_short
        self._ns_long = WP_NSMAP[ns_short]
        self._element_name = element_name
        self._cast = type
        self._uncast = str  # Good enough for type in (str, int)

    def _get_node(self, that):
        # So yeah, Demeter violations everywhere. Out of necessity,
        # XmlElementProperty is a "friend" of all the classes it applies to.
        nodes = that._elt.xpath("%s:%s" % (self._ns_short, self._element_name),
                                namespaces=WP_NSMAP)
        if len(nodes) == 1:
            return nodes[0]
        elif len(nodes) == 0:
            return None
        else:
            raise WXMLError("%d %s:%s's found, expected zero or one! - %s" %
                            (len(nodes), self._ns_short, self._element_name, repr(that)))

    def _get_or_create_node(self, that):
        node = self._get_node(that)
        if node is not None:
            return node
        new_node = lxml.etree.Element("{%s}%s" %
                                      (self._ns_long, self._element_name))
        that._elt.append(new_node)
        return new_node

    def __get__(self, that, unused_type_of_that=None):
        node = self._get_node(that)
        if node is None:
            return None
        value_text = node.text
        if not value_text:
            return None
        return self._cast(value_text)
        
    def __set__(self, that, newval):
        self._get_or_create_node(that).text = self._uncast(newval)

class Item:
    """An <item> in the XML document.

    An item represents a WordPress post of any type, including attachments,
    Polylang translation tables and even weirder instances (but see
    the all_pages class method).
    """
    @classmethod
    def all(cls, etree):
        return [cls(etree, elt) for elt in etree.xpath("//item")]

    @classmethod
    def insert(cls, etree):
        """Create and return an empty <item> element."""
        new_elt = lxml.etree.Element("item")
        existing_items = etree.xpath("//item")
        if existing_items:
            existing_items[0].addprevious(new_elt)
        else:
            etree.xpath("/rss/channel").append(new_elt)
        new_item = cls(etree, new_elt)
        new_item.parent_id = 0
        return new_item

    @classmethod
    def find_by_id(cls, etree, id):
        for item in cls.all(etree):
            if item.id == id:
                return item
        return None

    def __init__(self, etree, etree_elt):
        self._etree      = etree
        self._elt        = etree_elt

    id        = XmlElementProperty('wp', 'post_id',     int)
    parent_id = XmlElementProperty('wp', 'post_parent', int)
    post_name = XmlElementProperty('wp', 'post_name',   str)

    def delete(self):
        self._elt.getparent().remove(self._elt)
        for other in self.all(self._etree):
            if other.parent_id == self.id:
                other.parent_id = 0
        self._elt = 'DELETED'

    def ensure_id(self, int_direction = 1):
        if self.id is None:
            existing_ids = [item.id for item in self.all(self._etree)
                            if item.id is not None]
            if int_direction > 0:
                self.id = max([0] + existing_ids) + 1
            else:
                self.id = min([0] + existing_ids) - 1
        return self  # Chainable

    def __repr__(self):
        return '[Item %s]' % to_string(self._elt)

def demo():
    """For running in ipython - Not used in production"""
    import subprocess
    global srcdir, v, i
    srcdir = (subprocess.run("git rev-parse --show-toplevel", stdout=subprocess.PIPE, shell=True).stdout)[:-1]
    v = Ventilator("%s/wxr/cri.xml" % srcdir.decode("utf-8"))
    i = Item.insert(v.etree).ensure_id()
    print(repr(i.id))
    i.id = 5
    i.post_name = 'Just a test'
    print(repr(i.id))
    print(to_string(i))
    i.parent_id = 4
    i.post_name = 'Just another test'
    print(to_string(i))

    print("Before: %s" % to_string(Item.find_by_id(v.etree, 39)))
    Item.find_by_id(v.etree, 41).delete()  # 41 is parent of 39 in cri.xml
    print(to_string(Item.find_by_id(v.etree, 39)))

if __name__ == '__main__':
    args = docopt(__doc__)
    v = Ventilator(args["<input_xml_file>"])
    print(to_string(v.ventilate(args)))
