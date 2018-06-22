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
  wxr-ventilate.py [options] --new-site-url-base=<url> <input_xml_file>

Options:
   --filter        <url>
   --add-structure <relativeuri>

"""
from docopt import docopt
import lxml.etree
import phpserialize
from urllib.parse import urlparse, urlunparse
import logging

# https://stackoverflow.com/a/45488820/435004
try:
    from .basics import sole, sole_or_none, classproperty
except ModuleNotFoundError:
    from basics import sole, sole_or_none, classproperty


class WXRError(Exception):
    pass


def normalize_site_url(url_text):
    url = urlparse(url_text)
    # Believe it or not, _replace is public and documented API
    url = url._replace(scheme='https')
    if not url.path.endswith('/'):
        url = url._replace(path=url.path + '/')
    return urlunparse(url)


class Ventilator:
    def __init__(self, file, flags):
        self.etree = lxml.etree.parse(file)
        self.flags = flags

    @property
    def new_root_url(self):
        return normalize_site_url(self.flags['--new-site-url-base'])

    def ventilate(self):
        if self.flags['--filter']:
            raise Exception('UNIMPLEMENTED - Filter')
        if self.flags['--add-structure']:
            path_components = self.flags['--add-structure'].split('/')
            if path_components[0] == '':
                # Tolerate incorrect --add-parents=/foo/bar
                path_components.pop(0)
            homepage = self.homepageify()
            self.add_structure(path_components, homepage)

        return self.etree

    def homepageify(self):
        """Rearrange all pages under a single home page (modulo Polylang)."""

        homepage = Page.homepage(self.etree)
        if homepage is None:
            return

        translations = homepage.translations or {}
        do_not_reparent = set(t.id for t in homepage.translations_list)

        for page in Page.all(self.etree):
            if page.id not in do_not_reparent:
                page.parent_id = translations.get(page.language, homepage).id

        return homepage

    def add_structure(self, path_components, above_this_page):
        assert len(path_components) > 0

        previous_id = 0
        path_so_far = ""
        for path_component in path_components[:-1]:
            path_so_far += path_component + "/"
            structural_page = Page.insert_structural(
                self.etree, path_component)
            structural_page.guid = self.new_root_url + path_so_far
            structural_page.post_title = '[%s]' % path_component
            structural_page.parent_id = previous_id
            previous_id = structural_page.id

        for p in above_this_page.translations_list:
            p.parent_id = previous_id
            if p.id == above_this_page.id :
                distinctive_suffix = ''
            else:
                distinctive_suffix = '-' + p.language
            p.post_slug = path_components[-1] + distinctive_suffix


def to_string(what):
    if (isinstance(what, lxml.etree._Element) or
        isinstance(what, lxml.etree._ElementTree)):
        return lxml.etree.tostring(what, encoding='utf-8').decode('utf-8')
    else:
        return str(what)

class QName:
    """A Qualified Name for an XML element."""

    @classproperty
    def namespaces(cls):
        return {
            'content': 'http://purl.org/rss/1.0/modules/content/',
            'dc'     : 'http://purl.org/dc/elements/1.1/',
            'wp'     : 'http://wordpress.org/export/1.2/',
            'wfw'    : 'http://wellformedweb.org/CommentAPI/',
            'excerpt': 'http://wordpress.org/export/1.2/excerpt/'
        }

    @classmethod
    def xpath(cls, xpathable, xpath):
        return xpathable.xpath(xpath, namespaces=cls.namespaces)

    def __init__(self, shortform):
        self._shortform = shortform
        pieces = shortform.split(':')
        if len(pieces) == 1:
            self._ns_short     = None
            self._element_name = pieces[0]
        else:
            self._ns_short     = pieces[0]
            self._element_name = pieces[1]

    @classmethod
    def cast(cls, that):
        if isinstance(that, cls):
            return that
        else:
            return cls(str(that))

    @property
    def short(self):
        return self._shortform

    @property
    def long(self):
        if self._ns_short is not None:
            return "{%s}%s" % (self.namespaces[self._ns_short],
                               self._element_name)
        else:
            return self._element_name

    def __str__(self):
        return self._shortform


class XmlElementProperty:
    """A property for XML sub-element getters/setters."""
    def __init__(self, element_name, type=str):
        """Returns: An object with __get__ and __set__ methods that does the
        combined work of @property / @xx.setter for a datum of type
        `type' materialized as an XML child element `element_name'
        """
        self._qname = QName.cast(element_name)
        self._cast = type
        self._uncast = str  # Good enough for type in (str, int)

    def _elt(self, that):
        # Out of necessity, XmlElementProperty is a "friend" of all
        # the classes it applies to. This method concentrates the
        # required Demeter violations in a single place.
        return that._elt

    def _get_node(self, that):
        return sole_or_none(QName.xpath(self._elt(that),self._qname.short))

    def _get_or_create_node(self, that):
        node = self._get_node(that)
        if node is not None:
            return node
        new_node = lxml.etree.Element(self._qname.long)
        self._elt(that).append(new_node)
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


class XMLElement:
    """Abstract base class for Item and Channel."""
    def __init__(self, etree, etree_elt):
        """Private constructor, don't call directly."""
        self._etree      = etree
        self._elt        = etree_elt

    @classmethod
    def all(cls, etree):
        return [cls(etree, elt)
                for elt in QName.xpath(etree, '//' + cls.element_name)]


class Item(XMLElement):
    """An <item> in the XML document.

    An item represents a WordPress post of any type, including attachments,
    Polylang translation tables and even weirder instances (but see
    Page class).
    """

    element_name = 'item'  # Used by superclass

    id             = XmlElementProperty('wp:post_id',        int)
    parent_id      = XmlElementProperty('wp:post_parent',    int)
    guid           = XmlElementProperty('guid',              str)
    link           = XmlElementProperty('link',              str)
    post_title     = XmlElementProperty('title',             str)
    post_slug      = XmlElementProperty('wp:post_name',      str)
    post_type      = XmlElementProperty('wp:post_type',      str)
    post_status    = XmlElementProperty('wp:status',         str)
    comment_status = XmlElementProperty('wp:comment_status', str)
    ping_status    = XmlElementProperty('wp:ping_status',    str)

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
        return sole_or_none(item for item in cls.all(etree)
                            if item.id == id)

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


class Channel(XMLElement):
    """A <channel> element in the XML document."""

    element_name = 'channel'  # Used by superclass

    base_url = XmlElementProperty('link', str)

    @classmethod
    def the(cls, etree):
        return sole(cls.all(etree))

class Term(XMLElement):
    element_name = 'wp:term'

    taxonomy    = XmlElementProperty('wp:term_taxonomy', str)
    slug        = XmlElementProperty('wp:term_slug',     str)
    name        = XmlElementProperty('wp:term_name',     str)
    description = XmlElementProperty('wp:term_name',     str)

    @classmethod
    def find(cls, etree, taxonomy, slug):
        return sole_or_none(
            term for term in cls.all(etree)
            if term.taxonomy == taxonomy and term.slug == slug)


class ElementSubset:
    """Abstract superclasses for model classes that deal with a subset
       of instances of one of the XMLElement subclasses (e.g. Page for Item;
       Translation for Term).
    """
    def __init__(self, delegate):
        """Object constructor.

        Arguments:
            item: The XMLElement instance that represents this ElementSubset
                  instance. We use delegation, not inheritance: e.g.,
                  a Page object has-a Item.
        """
        self._delegate = delegate

    def __getattr__(self, attr):
        """Delegate whenever an unknown method is called."""
        return getattr(self._delegate, attr)

    def __setattr__(self, attr, newval):
        """Delegate setter iff the attribute exists in delegate."""
        if attr == '_delegate' or not hasattr(self._delegate, attr):
            return object.__setattr__(self, attr, newval)
        else:
            return setattr(self._delegate, attr, newval)


class Page(ElementSubset):
    """Model for WordPress "actual" pages (with post_type='page')."""
    @classmethod
    def all(cls, etree):
        return [cls(i) for i in Item.all(etree) if i.post_type == 'page']

    @classmethod
    def insert_structural(cls, etree, post_slug):
        new_item = Item.insert(etree)
        new_item.ensure_id(-1)
        new_item.post_type      = 'page'
        new_item.post_status    = 'publish'
        new_item.ping_status    = 'closed'
        new_item.comment_status = 'closed'
        new_item.post_slug      = post_slug
        return cls(new_item)

    @classmethod
    def by_id(cls, etree, id):
        this_item = sole_or_none(i for i in Item.all(etree)
                                 if i.id == id)
        assert this_item is None or this_item.post_type == 'page'
        return None if this_item is None else cls(this_item)

    @classmethod
    def homepage(cls, etree):
        def url_sig(url):
            return urlparse(url.rstrip('/')).path

        homepage_path = url_sig(Channel.the(etree).base_url)
        return sole(p for p in cls.all(etree)
                    if homepage_path == url_sig(p.link))

    @property
    def language(self):
        """The Polylang language set for this page.

        Returns: A string like "fr" or "en", or None."""
        category_ptr = sole_or_none(
            self._elt.xpath('category[@domain="language"]'))
        if category_ptr is None:
            return None
        else:
            return sole(category_ptr.xpath('@nicename'))

    @property
    def _translation_slug(self):
        """Returns: A string that looks like pll_1234567abcdef."""
        category_ptr = sole_or_none(
            self._elt.xpath('category[@domain="post_translations"]'))
        if category_ptr is None:
            return None
        else:
            return sole(category_ptr.xpath('@nicename'))

    @property
    def translations(self):
        """Returns: A dict mapping languages to Page instances."""
        translation_slug = self._translation_slug
        if translation_slug is None:
            return None
        else:
            return TranslationSet.find_by_slug(self._etree, translation_slug).posts

    @property
    def translations_list(self):
        translations = self.translations
        if translations is None:
            return [self]
        retval = list(translations.values())
        if self.id not in set(t.id for t in retval):
            logging.warn('Incoherent Polylang data - %s missing in its own translation list!', t)
            retval.append(self)
        return retval

    def __repr__(self):
        return '<Page post_id=%d post_slug="%s">' % (self.id, self.post_slug)

class TranslationSet(ElementSubset):
    """Model for a term of Polylang's post_translations taxonomy."""

    @classmethod
    def find_by_slug(cls, etree, slug):
        term = Term.find(etree, 'post_translations', slug)
        if term is None:
            return None
        else:
            return cls(term)

    @property
    def _payload(self):
        payload_text = sole(QName.xpath(self._elt, 'wp:term_description')).text.encode('utf-8')
        return phpserialize.loads(payload_text, decode_strings=True)

    @property
    def posts(self):
        translation_ids = self._payload
        if translation_ids is None:
            return None
        return {lang: Page.by_id(self._etree, id)
                 for (lang, id) in translation_ids.items() }

    def __repr__(self):
        return repr(self._payload)


if __name__ == '__main__':
    args = docopt(__doc__)
    v = Ventilator(args["<input_xml_file>"], args)
    print(to_string(v.ventilate()))


def demo():
    """For running in ipython - Not used in production"""
    import subprocess
    global srcdir, v, i, wxrdir, secure_it
    srcdir = (subprocess.run("git rev-parse --show-toplevel", stdout=subprocess.PIPE, shell=True).stdout)[:-1]
    wxrdir = '%s/wxr/' % srcdir.decode('utf-8')
    v = Ventilator(wxrdir + 'cri.xml', {})
    i = Item.insert(v.etree).ensure_id()
    print(repr(i.id))
    i.id = 5
    i.post_slug = 'Just a test'
    print(repr(i.id))
    print(to_string(i))
    i.parent_id = 4
    i.post_slug = 'Just another test'
    print(to_string(i))

    print("Before: %s" % to_string(Item.find_by_id(v.etree, 39)))
    Item.find_by_id(v.etree, 41).delete()  # 41 is parent of 39 in cri.xml
    print(to_string(Item.find_by_id(v.etree, 39)))

    secure_it = lxml.etree.parse(wxrdir + 'secure-it-next.xml')
    pages = Page.all(secure_it)
    translations = pages[0].translations
