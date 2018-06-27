"""Model for the WordPress XML format (WXR)

WXR is short for WordPress eXtended RSS.
"""

import logging
import phpserialize
from urllib.parse import urlparse
import lxml.etree

from basics import sole, sole_or_none, Delegator
import xml

__all__ = ('Channel', 'NavMenu', 'NavMenuItem', 'Page')

wp_namespaces = xml.XMLNamespaces(
    content='http://purl.org/rss/1.0/modules/content/',
    dc=     'http://purl.org/dc/elements/1.1/',
    wp=     'http://wordpress.org/export/1.2/',
    wfw=    'http://wellformedweb.org/CommentAPI/',
    excerpt='http://wordpress.org/export/1.2/excerpt/')

XMLElement         = wp_namespaces.XMLElement
XMLElementProperty = wp_namespaces.XMLElementProperty
XMLDictProperty    = wp_namespaces.XMLDictProperty


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

    def set_nicename(self, new_value, category_domain):
        category_ptr = sole_or_none(
            self._elt.xpath('category[@domain="%s"]' % category_domain))
        if category_ptr is None:
            category_ptr = lxml.etree.Element('category')
            self._elt.append(category_ptr)
            category_ptr.attrib['domain'] = category_domain
        category_ptr.attrib['nicename'] = new_value
        category_ptr.text = new_value

    @classmethod
    def insert_structural(cls, etree):
        """Insert and return a structural (fake) Item with negative ID."""
        new_item = cls.insert(etree)
        new_item.ensure_id(-1)
        new_item.post_status    = 'publish'
        new_item.ping_status    = 'closed'
        new_item.comment_status = 'closed'
        return new_item


class Channel(XMLElement):
    """A <channel> element in the XML document."""

    element_name = 'channel'

    base_url    = XMLElementProperty('link',        str)
    description = XMLElementProperty('description', str)

    @classmethod
    def the(cls, etree):
        return sole(cls.all(etree))

    @property
    def moniker(self):
        description = self.description and self.description.strip()
        if description not in (None, '', 'EPFL'):
            return description
        return self.base_url.replace('https://', '')

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


class XMLElementSubset(Delegator):
    """Abstract base class for model classes that deal with a subset
       of instances of one of the XMLElement subclasses (e.g. Page for Item;
       Translation for Term).
    """
    def __repr__(self):
        return '[%s %s]' % (self.__class__.__name__,
                            xml.xml_to_string(self._elt))


class ItemSubset(XMLElementSubset):
    """An XMLElementSubset of Item's, filtered by their <wp:post_type>."""
    @classmethod
    def all(cls, etree):
        return (cls(i) for i in Item.all(etree) if i.post_type == cls.POST_TYPE)

    @classmethod
    def insert_structural(cls, etree):
        new_item = Item.insert_structural(etree)
        new_item.post_type = cls.POST_TYPE
        return cls(new_item)


class TaxonomySubset(XMLElementSubset):
    """An XMLElementSubset of <wp:term>s of a particular taxonomy."""

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
        payload_text = sole(wp_namespaces.xpath(self._elt, 'wp:term_description')).text.encode('utf-8')
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
    """An <item> that has <wp:post_type>nav_menu_item</wp:post_type>."""

    POST_TYPE = 'nav_menu_item'

    title          = Item.post_title
    menu_item_type = Item.post_meta.property('_menu_item_type',            str)
    url            = Item.post_meta.property('_menu_item_url',             str)
    # post_id is the post this NavMenuItem refers to (if its
    # .menu_item_type is 'post_type'); parent_id is the ID of
    # the parent NavMenuItem, if any.
    # Near as I can tell, the "true" post_id and parent_id fields (the
    # ones we would delegate to Item absent the following) are unused by
    # wordpress-importer.php
    post_id       = Item.post_meta.property('_menu_item_object_id',        int)
    parent_id     = Item.post_meta.property('_menu_item_menu_item_parent', int)

    @property
    def menu_slug(self):
        """The short name ("slug") of the menu this item is part of."""
        return self.get_nicename(category_domain='nav_menu')

    @menu_slug.setter
    def menu_slug(self, newval):
        return self.set_nicename(newval, category_domain='nav_menu')
