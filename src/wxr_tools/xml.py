"""General-purpose XML toolbox and object mapper."""

import inspect
import lxml.etree


class XMLElement:
    """Abstract base class for an XML-backed model class.

    Subclasses must define the following attributes:

    element_name    The XPath notation of the element name, e.g.
                    "wp:term"
    """
    def __init__(self, etree, etree_elt):
        """Private constructor, don't call directly."""
        self._etree = etree
        self._elt = etree_elt

    @classmethod
    def all(cls, etree, namespaces=None):
        """Returns: All elements in etree with the class' element_name."""
        if namespaces is None:
            namespaces = XMLNamespaces.none()
        return [cls(etree, elt)
                for elt in namespaces.xpath(etree, '//' + cls.element_name)]

    def delete(self):
        self._elt.getparent().remove(self._elt)
        self._elt = 'DELETED'

    def __repr__(self):
        return '[%s %s]' % (self.__class__.__name__, xml_to_string(self._elt))


class XMLElementProperty:
    """Getter/setter for a property materialized in an XML element.

    Instances of XMLElementProperty are to be affixed to a class
    like this:

        class Item(XMLElement):
            # ...
            id = XMLElementProperty('wp:post_id', int)

    This makes it possible to get / set Item().id as an integer.
    """
    def __init__(self, element_name, type=str, namespaces=None, cdata=False):
        """Object constructor.

        Arguments:
           element_name: A (possibly namespaced) element name as a string,
                         e.g. "wp:post_id"
           type:         One of int or str

        Returns: An object with appropriate API to make things work
        magically behind the scenes.
        """
        self._element_name = element_name
        self._namespaces = namespaces or XMLNamespaces.none()
        self._cast = type
        self._uncast = str  # Good enough for type in (str, int)
        self.cdata = cdata

    def _elt(self, that):
        # Out of necessity, XMLElementProperty is a "friend" of all
        # the classes it applies to. This method concentrates the
        # required Demeter violations in a single place.
        return that._elt

    def _get_node(self, that):
        nodes = iter(self._namespaces.xpath(
            self._elt(that), self._element_name))
        try:
            return next(nodes)
        except StopIteration:
            return None

    def _get_or_create_node(self, that):
        node = self._get_node(that)
        if node is not None:
            return node
        new_node = self._namespaces.new_element(self._element_name)
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
        newtext = self._uncast(newval)
        if self.cdata:
            newtext = lxml.etree.CDATA(newtext)
        self._get_or_create_node(that).text = newtext


class XMLDictProperty:
    """Getter/setter for a dict-valued property materialized in XML.

    Instances of XMLDictProperty are to be affixed to a class
    like this:

        class Item(XMLElement):
            # ...
            post_meta = XMLDictProperty('wp:postmeta',
                                        'wp:meta_key', 'wp:meta_value')

    This makes it possible to get / set Item().post_meta as if it were
    a dict. XML-side, the contents of post_meta is represented as a
    list of key-value pairs.

    Additionally, one can fashion a scalar-valued property (int or
    str) out of a single key in the dict like this:

           menu_item_type = post_meta.property('_menu_item_type', str)

    """
    def __init__(self, *args, **kwargs):
        self._namespaces = kwargs.get('namespaces', XMLNamespaces.none())
        self._instance_constructor_params = (args, kwargs)

    def __get__(self, that, unused_type_of_that=None):
        if that is None:
            # Accessing the property on the class, rather than an instance
            return self  # So that one can alias us, or invoke .property on us

        args, kwargs = self._instance_constructor_params
        # Out of necessity, XMLDictProperty is a "friend" of all
        # the classes it applies to. We need but two Demeter violations
        # just here.
        xml_elt = that._elt
        xml_etree = that._etree
        return self._XMLDictPropertyInstance(
            xml_elt, xml_etree, *args, **kwargs)

    def __set__(self, that, what):
        the_dict = self.__get__()
        for(k, v) in what.items():
            the_dict[k] = v

    def property(self, key_name, type=str):
        return self._XMLDictItemProperty(self, key_name, type)

    class _XMLDictPropertyInstance:
        """What an XMLDictProperty "becomes" upon instantiating the class."""
        def __init__(self, _xml_elt, _xml_etree,
                     pair_element_name, key_element_name, value_element_name,
                     namespaces):
            assert isinstance(namespaces, XMLNamespaces)
            self._elt = _xml_elt
            self._etree = _xml_etree
            self._namespaces = namespaces
            self._pair_element_name = pair_element_name
            self._kvclass = type(
                '_XMLDictPropertyInstance_KVPair',
                (XMLElement, ),
                {'key': XMLElementProperty(
                    key_element_name,   str, namespaces=namespaces),
                 'value': XMLElementProperty(
                     value_element_name, str, namespaces=namespaces)
                 })

        def _all_kvpairs(self):
            lxml_nodes = self._namespaces.xpath(
                self._elt, self._pair_element_name)
            return (self._kvclass(self._etree, node) for node in lxml_nodes)

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
            new_node = self._namespaces.new_element(self._pair_element_name)
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
        """A scalar get/set property made out of one dict field."""
        def __init__(self, dict_property, key_name, type):
            self._dprop = dict_property
            self._key_name = key_name
            self._cast = type
            self._uncast = str  # Good enough for type in (str, int)

        def __get__(self, that, unused_type_of_that=None):
            val_raw = self._dprop.__get__(that).get(self._key_name, None)
            return None if val_raw is None else self._cast(val_raw)

        def __set__(self, that, newval):
            self._dprop.__get__(that)[self._key_name] = self._uncast(newval)


class XMLNamespaces:
    """Make namespaces a snap.

    There is some confusion inside XML standards stemming from the
    fact that the unique identifier of a namespace is its URL (the one
    that appears as the value of xmlns:foo=""), not its short name
    (foo). Yet a number of APIs, first and foremost XPath, only accept
    the short form and leave the mapping to be represented in some
    global variable.

    This class acts as said global variable. An instance represents a
    map from short names ("foo") to URLs ("https://fooxml.com/DTD/v1"
    or something), and streamlines the process of passing that map
    to lxml as appropriate.

    Attributes:
        XMLElement
        XMLElementProperty
        XMLDictProperty
                Identical to the respective top-level classes, except
                that the `namespaces` parameters of constructors and
                class methods default to this instance.
    """

    def __init__(self, **ns_map):
        self._ns_map = ns_map
        self.XMLElement = self._wrap_class(XMLElement, ['all'])
        self.XMLElementProperty = self._wrap_class(XMLElementProperty,
                                                   ['__init__'])
        self.XMLDictProperty = self._wrap_class(XMLDictProperty,
                                                ['__init__'])

    def _wrap_class(self, klass, methods):
        """Wrap `klass` so that it can stop worrying about namespaces.

        Returns: A class that looks and works just like `klass`, except
        that class methods / constructors named in `methods` will be
        called have their `namespaces` keyword argument defaulting to this
        XMLNamespaces instance (instead of XMLNamespaces.none(), as
        is the case for the pristine classes).
        """

        def wrap_method(klass, method_name):
            method = getattr(klass, method_name)

            def wrapper_for_instance_method(*args, **kwargs):
                if 'namespaces' not in kwargs:
                    kwargs['namespaces'] = self
                return method(*args, **kwargs)

            def wrapper_for_class_method(*args, **kwargs):
                if 'namespaces' not in kwargs:
                    kwargs['namespaces'] = self
                # Must unwrap the "real" __func__, as `method` closes
                # over the original (unwrapped) class through its
                # `cls` parameter
                return method.__func__(*args, **kwargs)

            if inspect.ismethod(method):
                wrapped = classmethod(wrapper_for_class_method)
            else:
                wrapped = wrapper_for_instance_method
            wrapped.__name__ = method_name
            return wrapped

        return type(
            klass.__name__,    # Mimic the name
            (klass, ),         # Inherit from it
            {method_name: wrap_method(klass, method_name)
             for method_name in methods})

    @classmethod
    def none(cls):
        """Returns: An empty mapping with no namespaces."""
        return cls()

    def xpath(self, xpathable, xpath):
        """Like the .xpath method of lxml.Element etc, with namespaces"""
        return xpathable.xpath(xpath, namespaces=self._ns_map)

    def new_element(self, short_name):
        """Like the lxml.etree.Element constructor, with namespaces"""
        return lxml.etree.Element(self._qualify(short_name))

    def _qualify(self, short_name):
        """Returns: An element name in the {url}foo notation."""
        pieces = short_name.split(':')
        if len(pieces) == 1:
            return pieces[0]
        else:
            (ns_short, element_name) = pieces
            return "{%s}%s" % (self._ns_map[ns_short],
                               element_name)


def xml_to_string(what):
    return lxml.etree.tostring(what, encoding='utf-8').decode('utf-8')
