""" (c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    This script automates the crawling of Jahia website in order to download zip exports.
"""

import logging
import timeit
import requests
from collections import OrderedDict
from datetime import timedelta
from clint.textui import progress

from .config import JahiaConfig
from .session import SessionHandler


class JahiaCrawler(object):

    def __init__(self, site, session=None, username=None, password=None,
                 host=None, date=None, zip_path=None, force=False):
        self.site = site
        self.config = JahiaConfig(site, host=host, date=date, zip_path=zip_path)
        self.skip_download = self.config.already_downloaded and not force
        self.session_handler = session or SessionHandler(username=username, password=password, host=host)

    def download_site(self):
        # do not download twice if not force
        if self.skip_download:
            files = self.config.existing_files
            file_path = files[-1]
            logging.info("%s - zip already downloaded %sx. Last one is %s",
                         self.site, len(files), file_path)
            return file_path

        # set timer to measure execution time
        start_time = timeit.default_timer()

        # make query
        logging.info("%s - downloading %s...", self.site, self.config.file_name)
        response = self.session_handler.session.post(
            self.config.file_url,
            params=self.config.download_params,
            stream=True
        )
        logging.debug("%s - %s => %s", self.site, response.url, response.status_code)

        # raise exception in case of error
        if not response.status_code == requests.codes.ok:
            response.raise_for_status()

        # adapt streaming function to content-length in header
        logging.debug("%s - headers %s", self.site, response.headers)
        total_length = response.headers.get('content-length')
        if total_length is not None:
            def read_stream():
                return progress.bar(
                    response.iter_content(chunk_size=4096),
                    expected_size=(int(total_length) / 4096) + 1)
        else:
            def read_stream():
                return response.iter_content(chunk_size=4096)

        # download file
        logging.info("%s - saving response into %s...", self.site, self.config.file_path)
        with open(self.config.file_path, 'wb') as output:
            for chunk in read_stream():
                if chunk:
                    output.write(chunk)
                    output.flush()

        # log execution time and return path to downloaded file
        elapsed = timedelta(seconds=timeit.default_timer() - start_time)
        logging.info("%s - file downloaded in %s", self.site, elapsed)

        # return PosixPath converted to string
        return str(self.config.file_path)


def download_many(sites, session=None, username=None, password=None, host=None, zip_path=None, force=False):
    """ returns list of downloaded_files """
    # to store paths of downloaded zips
    downloaded_files = OrderedDict()

    # use same session for all downloads
    session = session or SessionHandler(username=username, password=password, host=host)

    # download sites from sites
    for site in sites:
        try:
            crawler = JahiaCrawler(site, session=session, zip_path=zip_path, force=force)
            downloaded_files[site] = crawler.download_site()
        except Exception as err:
            logging.error("%s - crawl - Could not crawl Jahia - Exception: %s", site, err)

    # return results, as strings
    return downloaded_files
