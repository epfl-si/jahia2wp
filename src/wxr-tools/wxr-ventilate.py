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
from urllib.parse import urlparse, urlunparse
import logging

# https://stackoverflow.com/a/45488820/435004
try:
    from .wxr_model import *
    from .xml import xml_to_string
except ModuleNotFoundError:
    from wxr_model import *
    from xml import xml_to_string


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

            self.trim_and_reparent_menus()

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

    def trim_and_reparent_menus(self):
        """Ensure that menus will import/merge sanely.

        * Remove all menus except the "main" one (in all languages)

        * Reparent everything in said menu, so that it becomes easier to
          manipulate the imported menu in the stock wp-admin menu editor
        """

        will_reparent_under = {}
        for menu in NavMenu.all(self.etree):
            slug = menu.slug
            if slug.startswith('main'):
                new_nav = will_reparent_under[slug] = NavMenuItem.insert_structural(self.etree)
                new_nav.menu_slug       = slug
                new_nav.menu_item_type  = 'custom'
                new_nav.url             = '#'
                new_nav.title           = '["%s" menu of %s]' % (slug, Channel.the(self.etree).moniker)
            else:
                menu.delete()

        for nav in NavMenuItem.all(self.etree):
            if nav.menu_slug not in will_reparent_under:
                nav.delete()  # We already tossed the menu it belongs to
                continue
            if nav.parent_id == 0:
                nav.parent_id = will_reparent_under[nav.menu_slug].id


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


if __name__ == '__main__':
    args = docopt(__doc__)
    v = Ventilator(args["<input_xml_file>"], args)
    print(xml_to_string(v.ventilate()))


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
    print(xml_to_string(i))
    i.parent_id = 4
    i.post_slug = 'Just another test'
    print(xml_to_string(i))

    print("Before: %s" % xml_to_string(Item.find_by_id(v.etree, 39)))
    Item.find_by_id(v.etree, 41).delete()  # 41 is parent of 39 in cri.xml
    print(xml_to_string(Item.find_by_id(v.etree, 39)))

    secure_it = lxml.etree.parse(wxrdir + 'secure-it-next.xml')
    pages = Page.all(secure_it)
    translations = next(iter(pages)).translations
