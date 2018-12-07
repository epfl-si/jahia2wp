#!/usr/bin/env python3
# -*- coding: utf-8; -*-
# All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

"""🎶♪♩ Come on baby, do the ventilation ♩🎶♪

Usage:
  ventilate.py [options] <ventilation_csv_file> <wxr_sourcedir> <wxr_destdir>

Options:
     --quiet
     --debug
"""

import os
import sys
import lxml.etree
from urllib.parse import urlparse, urlunparse

from docopt import docopt
import logging

dirname = os.path.dirname
sys.path.append(dirname(dirname(os.path.realpath(__file__))))

from utils import Utils                                          # noqa: E402
from wordpress_inventories import site_moniker, VentilationTodo  # noqa: E402
from wxr_tools.wxr_ventilate import Ventilator  # noqa: E402
from wxr_tools.xml import xml_to_string  # noqa: E402


def _increment_xml_file_path(xml_file_path):
    """
    Return the next incremental name xml file.

    Example:
    input: xml_file_path: help-actu_1.xml
    output: help-actu_2.xml

    :param xml_file_path: path of xml file

    :return: next incremental name xml name
    """
    index = 1
    path = xml_file_path.replace(".xml", "") + "_{}.xml"
    while os.path.exists(path.format(index)):
        index += 1
    return path.format(index)


class SourceWXR:
    """Model for one of the files in <wxr_sourcedir>"""

    def __init__(self, path):
        self.path = path

    @property
    def _etree(self):
        if not hasattr(self, '_cached_etree'):
            self._cached_etree = lxml.etree.parse(self.path)
        return self._cached_etree

    @property
    def root_url(self):
        url_obj = urlparse(self._etree.xpath('/rss/channel/link')[0].text)
        return urlunparse(url_obj._replace(scheme='https')).rstrip('/') + '/'

    def intersects(self, pattern):
        """True iff `pattern' can match any page in the WordPress site of this WXR file.

        Args:
          pattern: A pattern excerpted from the left-hand-side column
                   (`source') of the ventilation CSV file
        """
        return pattern.startswith(self.root_url)

    def __repr__(self):
        return '<%s "%s">' % (self.__class__.__name__, self.path)


class DestinationWXR:
    """Model for one of the files in <wxr_destdir>"""

    def __init__(self, dest_file, source_wxr):
        self.source_file = source_wxr.path
        self.path = dest_file

    def create(self, filter, relative_uri, destination_site, source_url):

        os.makedirs(dirname(self.path), exist_ok=True)

        ventilator = Ventilator(
            file=self.source_file,
            new_site_url_base=destination_site,
            filter=filter,
            url_rewrite_from=source_url,
            url_rewrite_to=destination_site,
            relative_uri=relative_uri
        )

        output = ventilator.ventilate()

        with open(self.path, 'w') as file:
            file.write(xml_to_string(output))


if __name__ == '__main__':
    args = docopt(__doc__)

    os.environ['WP_ENV'] = 'ventilate'   # Lest the next line raise an exception
    Utils.set_logging_config(args)

    tasks = VentilationTodo(args['<ventilation_csv_file>']).items

    os.makedirs(args['<wxr_destdir>'])  # Should *not* already exist

    for xml_dirent in os.scandir(args['<wxr_sourcedir>']):

        if not xml_dirent.name.endswith('.xml'):
            logging.warning('Skipping %s', xml_dirent.name)
            continue

        source_wxr = SourceWXR(xml_dirent.path)
        source_moniker = site_moniker(source_wxr.root_url)
        logging.debug('Processing source WXR file %s for %s ("%s")',
                      source_wxr.path, source_wxr.root_url, source_moniker)

        output_count_for_this_source_wxr = 0
        for task in tasks:

            if not source_wxr.intersects(task.source_pattern):
                continue

            dest_moniker = site_moniker(task.destination_site)

            destination_xml_path = '%s/%s/%s.xml' % (
                args['<wxr_destdir>'],
                dest_moniker,
                source_moniker
            )

            if os.path.exists(destination_xml_path):
                destination_xml_path = _increment_xml_file_path(destination_xml_path)

            destination_wxr = DestinationWXR(destination_xml_path, source_wxr)

            destination_wxr.create(
                filter=task.source_pattern,
                relative_uri=task.relative_uri,
                destination_site=task.destination_site,
                source_url=task.source_url
            )

            output_count_for_this_source_wxr += 1

        logging.info(
            'Created %d XML files for %s',
            output_count_for_this_source_wxr,
            source_moniker
        )