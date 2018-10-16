"""Ventilation-related HTML manipulation"""

import re
from bs4 import BeautifulSoup, CData
import logging
import os
from urllib.parse import urlparse


def fix_links(html, source_url_site, destination_url_site, relative_uri):
    """
    Fix absolute and relative links in the content of page

    :param html: The HTML of a page
    :param source_url_site: Source URL site
    :param destination_url_site: Destination URL site
    :param relative_uri: Relative URI of destination
    """

    logging.info("Starting fix links...")

    # parse html content of xml 'content:encoded' tag
    soup_html = BeautifulSoup(html, 'html5lib')

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
    return str(soup_html)
