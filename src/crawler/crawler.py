""" (c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    This script automates the crawling of Jahia website in order to download zip exports.
"""

import logging
import os
import timeit
from collections import OrderedDict
from datetime import timedelta
from pathlib import Path

import requests
from clint.textui import progress


class JahiaCrawler(object):
    """
        Call JahiaCrawler.download(cmd_args)

        'cmd_args' drives the download logic, i.e:
        * '--site': what unic site to download
        * '-n' & '-s': or how many sites to download from jahia_sites, where from
        * '--date': what date should be asked to Jahia
        * '--force': if existing downloaded files should be overriden or not
     """

    def __init__(self, site_name, cmd_args):
        self.site_name = site_name
        # jahia download URI depends on date
        self.date = cmd_args['--date']
        # where to store zip files
        self.export_path = cmd_args['--export-path']
        # where to store output from the script (tracer)
        self.output_path = cmd_args['--output-dir']
        # whether overriding existing zip or not
        self.force = cmd_args['--force-crawl']
        # to measure overall download time for given site
        self.elapsed = 0

        # adapt file_path to cmd_args
        existing = self.already_downloaded()
        if existing and not self.force:
            self.file_path = existing[-1]
            self.file_name = os.path.basename(self.file_path)
        else:
            self.file_name = self.FILE_PATTERN % (self.site_name, self.date)
            self.file_path = os.path.join(self.export_path, self.file_name)

    def __str__(self):
        """ Format used for report"""
        return ";".join([self.site_name, self.file_path, str(self.elapsed)])

    def already_downloaded(self):
        path = Path(self.export_path)

        return [str(file_path) for file_path in path.glob("%s_export*" % self.site_name)]

    def download_site(self):
        # do not download twice if not --force
        existing = self.already_downloaded()
        if existing and not self.force:
            logging.warning("%s already downloaded %sx. Last one is %s",
                            self.site_name, len(existing), self.file_path)
            return self.file_path

        # pepare query
        params = self.DWLD_GET_PARAMS.copy()
        params['sitebox'] = self.site_name

        # set timer to measure execution time
        start_time = timeit.default_timer()

        # make query
        logging.debug("downloading %s...", self.file_name)
        response = self.session.post(
            "%s/%s/%s" % (self.HOST, self.DWLD_URI, self.file_name),
            params=params,
            stream=True
        )
        logging.debug("requested %s", response.url)
        logging.debug("returned %s", response.status_code)

        # raise exception in case of error
        if not response.status_code == requests.codes.ok:
            response.raise_for_status()

        # adapt streaming function to content-length in header
        logging.debug("headers %s", response.headers)
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
        logging.info("saving response into %s...", self.file_path)
        with open(self.file_path, 'wb') as output:
            for chunk in read_stream():
                if chunk:
                    output.write(chunk)
                    output.flush()

        # log execution time and return path to downloaded file
        self.elapsed = timedelta(seconds=timeit.default_timer() - start_time)
        logging.info("file downloaded in %s", self.elapsed)
        tracer_path = os.path.join(self.output_path, self.TRACER)
        with open(tracer_path, 'a') as tracer:
            tracer.write(str(self))
            tracer.flush()

        # return PosixPath converted to string
        return self.file_path


def download_many(jahia_sites, cmd_args):
    """
        Download either the one site 'cmd_args.site_name'
        or all 'cmd_args.number' sites from jahia_sites, starting at 'cmd_args.start_at'

        returns list of downloaded_files
    """
    logging.debug("DATE set to %s", cmd_args['--date'])

    # to store paths of downloaded zips
    downloaded_files = OrderedDict()

    # compute list fo sites to download
    try:
        start_at = jahia_sites.index(cmd_args['<site>'])
    except ValueError:
        raise SystemExit("site name %s not found in jahia_sites", cmd_args['<site>'])
    end = start_at + int(cmd_args['--number'])
    sites = jahia_sites[start_at:end]

    # download sites from jahia_sites
    for site in sites:
        try:
            downloaded_files[site] = str(JahiaCrawler(site, cmd_args).download_site())
        except Exception as err:
            logging.error("%s - crawl - Could not crawl Jahia - Exception: %s", site, err)

    # return results, as strings
    return downloaded_files
