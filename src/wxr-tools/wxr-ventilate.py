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
   --filter=<url>
   --add-structure=<relativeuri>

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
            logging.warn('UNIMPLEMENTED: --filter')
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


class XMLElementProperty:
    """Getter/setter for XML properties.

    Instances of XMLElementProperty are to be affixed to a class
    like this:

        class Item:   # For instance

            post_title = XMLElementProperty('title', str)

    will make it possible to get / set Item().post_title as a string.
    """
    def __init__(self, element_name, type=str):
        """Object constructor.

        Arguments:
           element_name: A QName instance or a namespaced element name as a
                         string, e.g. "wp:post-parent"
           type:         One of int or str

        Returns: An object with appropriate API to make things work
        magically behind the scenes.
        """
        self._qname = QName.cast(element_name)
        self._cast = type
        self._uncast = str  # Good enough for type in (str, int)

    def _elt(self, that):
        # Out of necessity, XMLElementProperty is a "friend" of all
        # the classes it applies to. This method concentrates the
        # required Demeter violations in a single place.
        return that._elt

    def _get_node(self, that):
        return sole_or_none(QName.xpath(self._elt(that), self._qname.short))

    def _get_or_create_node(self, that):
        node = self._get_node(that)
        if node is not None:
            return node
        new_node = lxml.etree.Element(self._qname.long)
        self._elt(that).append(new_node)
        return new_node

    def __get__(self, that, unused_type_of_that=None):
        if that is None:
            # Accessing the property on the class, rather than an instance
            return self  # Allows for aliasing the property across classes
        node = self._get_node(that)
        if node is None:
            return None
        value_text = node.text
        if not value_text:
            return None
        return self._cast(value_text)
        
    def __set__(self, that, newval):
        self._get_or_create_node(that).text = self._uncast(newval)


class XMLDictProperty:
    def __init__(self, *args, **kwargs):
        self.__instance_constructor_params = (args, kwargs)

    def __get__(self, that, unused_type_of_that=None):
        if that is None:
            # Accessing the property on the class, rather than an instance
            return self  # So that one can invoke .property on it

        args, kwargs = self.__instance_constructor_params
        # Out of necessity, XMLDictProperty is a "friend" of all
        # the classes it applies to. We need but two Demeter violations
        # just here.
        xml_elt   = that._elt
        xml_etree = that._etree
        return self._XMLDictPropertyInstance(xml_elt, xml_etree, *args, *kwargs)

    def __set__(self, that, what):
        the_dict = self.__get__()
        for(k, v) in what.items():
            the_dict[k] = v

    # Make a scalar property (that works like XMLElementProperty) out
    # of one particular field of this XMLDictProperty.
    def property(self, key_name, type=str):
        return self._XMLDictItemProperty(self, key_name, type)

    class _XMLDictPropertyInstance:
        def __init__(self, _xml_elt, _xml_etree,
                     pair_element_name, key_element_name, value_element_name):
            self._elt   = _xml_elt
            self._etree = _xml_etree
            self._qname = QName.cast(pair_element_name)
            self._kvclass = type(
                'XMLDictPropertyInstance_KVPair',
                (XMLElement, ),
                { 'key':   XMLElementProperty(key_element_name,   str),
                  'value': XMLElementProperty(value_element_name, str) })

        def _all_kvpairs(self):
            return (self._kvclass(self._etree, kvnode)
                    for kvnode in QName.xpath(self._elt, self._qname.short))

        def _find_kvpair(self, k):
            try:
                return next(kv for kv in self._all_kvpairs()
                            if kv.key == k)
            except StopIteration:
                return None

        def _find_or_create_kvpair(self, k):
            kv = self._find_kvpair(k)
            if kv:
                return kv
            new_node = lxml.etree.Element(self._qname.long)
            self._elt.append(new_node)
            new_kv = self._kvclass(self._etree, new_node)
            new_kv.key = k
            return new_kv

        def __getitem__(self, k):
            kv = self._find_kvpair(k)
            if kv:
                return kv.value
            else:
                raise KeyError(k)

        def __setitem__(self, k, v):
            self._find_or_create_kvpair(k).value = v

        def __delitem__(self, k):
            kv = self._find_kvpair(k)
            if not kv:
                return
            kv.delete()

        def keys(self):
            return (kv.v for kv in self._all_kvpairs())

        # Le sigh - https://stackoverflow.com/a/11165510/435004
        def get(self, k, default_v):
            try:
                return self.__getitem__(k)
            except KeyError:
                return default_v
        def __iter__(self):
            for kv in self._all_kvpairs():
                yield kv.key
        def __len__(self):
            return len(self._all_kvpairs())

    class _XMLDictItemProperty:
        def __init__(self, dict_property, key_name, type):
            self._dprop = dict_property
            self._key_name = key_name
            self._cast   = type
            self._uncast = str

        def __get__(self, that, unused_type_of_that=None):
            val_raw = self._dprop.__get__(that).get(self._key_name, None)
            return None if val_raw is None else self._cast(val_raw)

        def __set__(self, that, newval):
            self._dprop.__get__(that)[self._key_name] = self._uncast(newval)

class XMLElement:
    """Abstract base class for Item, Channel and more."""
    def __init__(self, etree, etree_elt):
        """Private constructor, don't call directly."""
        self._etree      = etree
        self._elt        = etree_elt

    @classmethod
    def all(cls, etree):
        return [cls(etree, elt)
                for elt in QName.xpath(etree, '//' + cls.element_name)]

    def delete(self):
        self._elt.getparent().remove(self._elt)
        self._elt = 'DELETED'

    def __repr__(self):
        return '[%s %s]' % (self.__class__.__name__, to_string(self._elt))


class Item(XMLElement):
    """An <item> in the XML document.

    An item represents a WordPress post of any type, including attachments,
    Polylang translation tables and even weirder instances (but see
    Page class).
    """

    element_name = 'item'  # Used by superclass

    id             = XMLElementProperty('wp:post_id',        int)
    parent_id      = XMLElementProperty('wp:post_parent',    int)
    guid           = XMLElementProperty('guid',              str)
    link           = XMLElementProperty('link',              str)
    post_title     = XMLElementProperty('title',             str)
    post_slug      = XMLElementProperty('wp:post_name',      str)
    post_type      = XMLElementProperty('wp:post_type',      str)
    post_status    = XMLElementProperty('wp:status',         str)
    comment_status = XMLElementProperty('wp:comment_status', str)
    ping_status    = XMLElementProperty('wp:ping_status',    str)

    post_meta = XMLDictProperty('wp:postmeta', 'wp:meta_key', 'wp:meta_value')

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
        id = self.id
        etree = self._etree
        super().delete()
        for other in self.all(etree):
            if other.parent_id == id:
                other.parent_id = 0

    def ensure_id(self, int_direction = 1):
        if self.id is None:
            existing_ids = [item.id for item in self.all(self._etree)
                            if item.id is not None]
            if int_direction > 0:
                self.id = max([0] + existing_ids) + 1
            else:
                self.id = min([0] + existing_ids) - 1
        return self  # Chainable

    def get_nicename(self, category_domain):
        category_ptr = sole_or_none(
            self._elt.xpath('category[@domain="%s"]' % category_domain))
        if category_ptr is None:
            return None
        else:
            return sole(category_ptr.xpath('@nicename'))

    @classmethod
    def insert_structural(cls, etree):
        new_item = cls.insert(etree)
        new_item.ensure_id(-1)
        new_item.post_status    = 'publish'
        new_item.ping_status    = 'closed'
        new_item.comment_status = 'closed'
        return new_item


class Channel(XMLElement):
    """A <channel> element in the XML document."""

    element_name = 'channel'

    base_url = XMLElementProperty('link', str)

    @classmethod
    def the(cls, etree):
        return sole(cls.all(etree))


class Term(XMLElement):
    """A <wp:term> element in the XML document."""

    element_name = 'wp:term'

    taxonomy    = XMLElementProperty('wp:term_taxonomy', str)
    slug        = XMLElementProperty('wp:term_slug',     str)
    name        = XMLElementProperty('wp:term_name',     str)
    description = XMLElementProperty('wp:term_name',     str)

    @classmethod
    def find(cls, etree, taxonomy, slug=None):
        return (
            term for term in cls.all(etree)
            if term.taxonomy == taxonomy and
            ((term.slug == slug) if slug is not None else True))


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
        """Delegate if appropriate."""
        if attr == '_delegate' or attr in self.__dict__ or attr in self.__class__.__dict__:
            return object.__getattr__(self, attr)
        else:
            return getattr(self._delegate, attr)

    def __setattr__(self, attr, newval):
        """Delegate if appropriate."""
        if attr == '_delegate' or attr in self.__dict__ or attr in self.__class__.__dict__:
            return object.__setattr__(self, attr, newval)
        else:
            return setattr(self._delegate, attr, newval)

    def __repr__(self):
        return '[%s %s]' % (self.__class__.__name__, to_string(self._elt))


class ItemSubset(ElementSubset):
    """An ElementSubset of Item where the criterion is the <wp:post_type>."""
    @classmethod
    def all(cls, etree):
        return (cls(i) for i in Item.all(etree) if i.post_type == cls.POST_TYPE)

    @classmethod
    def insert_structural(cls, etree):
        new_item = Item.insert_structural(etree)
        new_item.post_type = cls.POST_TYPE
        return cls(new_item)


class TaxonomySubset(ElementSubset):
    """An ElementSubset of <wp:term>s of a particular taxonomy."""

    @classmethod
    def all(cls, etree):
        return (cls(term) for term in Term.find(etree, cls.TAXONOMY_SLUG))

    @classmethod
    def find_by_slug(cls, etree, slug):
        term = sole_or_none(Term.find(etree, cls.TAXONOMY_SLUG, slug))
        if term is None:
            return None
        else:
            return cls(term)


class Page(ItemSubset):
    """Model for WordPress "actual" pages (with post_type='page')."""

    POST_TYPE = 'page'

    @classmethod
    def insert_structural(cls, etree, post_slug):
        new_item = super().insert_structural(etree)
        new_item.post_slug = post_slug
        return new_item

    @classmethod
    def by_id(cls, etree, id):
        this_item = sole_or_none(i for i in Item.all(etree)
                                 if i.id == id)
        assert this_item is None or this_item.post_type == cls.POST_TYPE
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
        return self.get_nicename(category_domain='language')

    @property
    def _translation_slug(self):
        """Returns: A string that looks like pll_1234567abcdef."""
        return self.get_nicename('post_translations')

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


class TranslationSet(TaxonomySubset):
    """Model for a term of Polylang's post_translations taxonomy."""

    TAXONOMY_SLUG = 'post_translations'

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


class NavMenu(TaxonomySubset):
    """Model for the navigation menu terms (the containers, not the items)."""
    TAXONOMY_SLUG = 'nav_menu'


class NavMenuItem(ItemSubset):
    """An <item> with <wp:post_type>nav_menu_item</wp:post_type>."""

    POST_TYPE = 'nav_menu_item'

    @property
    def menu_slug(self):
        """The short name ("slug") of the menu this item is part of."""
        return self.get_nicename(category_domain='nav_menu')

    @menu_slug.setter
    def menu_slug(self, newval):
        return self.set_nicename(newval, category_domain='nav_menu')

    title          = Item.post_title
    menu_item_type = Item.post_meta.property('_menu_item_type',            str)
    url            = Item.post_meta.property('_menu_item_url',             str)
    # post_id is the post this NavMenuItem refers to (if its
    # .menu_item_type is 'post_type'); parent_id is the ID of
    # the parent NavMenuItem, if any.
    # Near as I can tell, the "true" post_id and parent_id fields (the
    # ones we would delegate to Item absent the following) is unused by
    # wordpress-importer.php
    post_id       = Item.post_meta.property('_menu_item_object_id',        int)
    parent_id     = Item.post_meta.property('_menu_item_menu_item_parent', int)

if __name__ == '__main__':
    args = docopt(__doc__)
    v = Ventilator(args["<input_xml_file>"], args)
    print(to_string(v.ventilate()))


def demo():
    """For running in ipython - Not used in production"""
    import subprocess
    global srcdir, v, i, wxrdir, secure_it
    srcdir = (subprocess.run("git rev-parse --show-toplevel", stdout=subprocess.PIPE, shell=True).stdout)[:-1]
    wxrdir = '%s/src/ventilation/wxr-src/' % srcdir.decode('utf-8')
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
    translations = next(iter(pages)).translations
