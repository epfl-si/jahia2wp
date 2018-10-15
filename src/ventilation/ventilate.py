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
import re
import sys
import subprocess
import lxml.etree
from urllib.parse import urlparse, urlunparse

from bs4 import BeautifulSoup, CData
from docopt import docopt
import logging

dirname = os.path.dirname
sys.path.append(dirname(dirname(os.path.realpath(__file__))))

from utils import Utils                                          # noqa: E402
from wordpress_inventories import site_moniker, VentilationTodo  # noqa: E402


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

    def create(self, filter, add_structure, new_url):
        os.makedirs(dirname(self.path), exist_ok=True)
        wxr_ventilate_path = os.path.join(
            dirname(__file__),
            '../wxr_tools/wxr_ventilate.py')
        cmdline = [
            sys.executable,
            wxr_ventilate_path,
            '--new-site-url-base', new_url,
            '--filter', filter,
            '--add-structure', add_structure,
            self.source_file
        ]
        logging.debug(' '.join(cmdline))
        return subprocess.run(cmdline,
                              stdout=open(self.path, 'w'),
                              check=True)

    def fix_links(self, source_url_site, destination_url_site, relative_uri):
        """
        Fix absolute and relative links in the content of page

        :param source_url_site: Source URL site
        :param destination_url_site: Destination URL site
        :param relative_uri: Relative URI of destination
        """

        logging.info("Starting fix links...")

        with open(self.path, "r") as wxr_ventilated_xml_file:

            # parse xml
            soup = BeautifulSoup(wxr_ventilated_xml_file.read(), 'xml')

            # find all <content:encoded> xml tag
            content_encoded_tags = soup.find_all('content:encoded')

            for xml_tag in content_encoded_tags:

                if len(xml_tag.contents) == 0:
                    continue

                # parse html content of xml 'content:encoded' tag
                soup_html = BeautifulSoup(xml_tag.contents[0], 'html5lib')

                # delete html tags like <html>, <head>, <body>
                # and keeps only the HTML content
                soup_html.body.hidden = True
                soup_html.head.hidden = True
                soup_html.html.hidden = True

                tag_attribute_list = [('a', 'href'), ]

                for element in tag_attribute_list:

                    # find all HTML tags like 'a' or 'img'
                    html_tags = soup_html.find_all(element[0])

                    for html_tag in html_tags:

                        # get the attribute value
                        link = html_tag.get(element[1])

                        if not link:
                            continue

                        if link.startswith(source_url_site):
                            # absolute URLs
                            html_tag[element[1]] = link.replace(source_url_site, destination_url_site)
                            logging.debug("Replace absolute URL {} by {}".format(source_url_site, destination_url_site))

                        else:
                            # relative URLs
                            parsed = urlparse(source_url_site)
                            hostname = parsed.netloc.split('.')[0]

                            if hostname not in ('migration-wp', 'www2018'):
                                start_relative_path = "/" + hostname + "/"

                            else:
                                # example:
                                # https://migration-wp.epfl.ch/help-actu/**
                                # start_relative_path is 'help-actu'
                                start_relative_path = re.search('([^/]*)/?$', parsed.path).group(1)
                                start_relative_path = "/" + start_relative_path + "/"

                            if link.startswith(start_relative_path):

                                target_url = os.path.join(urlparse(destination_url_site).path + relative_uri)
                                if not target_url.endswith("/"):
                                    target_url += "/"

                                html_tag[element[1]] = link.replace(
                                    start_relative_path, target_url
                                    )

                                logging.debug(
                                    "Replace relative URL {} by {}".format(
                                        start_relative_path, target_url
                                    )
                                )

                # Add CDATA like this <content:encoded><![CDATA[ ... ]]></content:encoded>
                xml_tag.contents[0] = CData(str(soup_html))

            with open(self.path, "w") as wxr_ventilated_xml_file:
                wxr_ventilated_xml_file.write(str(soup))

            logging.info("End of fix links...")


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
                add_structure=task.relative_uri,
                new_url=task.destination_site
            )

            destination_wxr.fix_links(
                source_url_site=task.source_url,
                destination_url_site=task.destination_site,
                relative_uri=task.relative_uri
            )

            output_count_for_this_source_wxr += 1

        logging.info(
            'Created %d XML files for %s',
            output_count_for_this_source_wxr,
            source_moniker
        )
