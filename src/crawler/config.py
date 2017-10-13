""" (c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

    Helper class which holds all configuration parameters to connect to Jahia
"""
import os
from pathlib import Path
from datetime import datetime

# do not explicitly import variables
# this allows to reload settings at running time with different environment variable (e.g in tests)
import settings


class JahiaConfig(object):

    # elements of URI
    JAHIA_DOWNLOAD_URI = "administration/engineName/export"
    FILE_PATTERN = "%s_export_%s.zip"

    def __init__(self, site, host=None, date=None, zip_path=None):
        # site to crawl (jahia key)
        self.site = site

        # crawling parameters for HTTP request
        date = date or datetime.today()
        self.date = date.strftime("%Y-%m-%d-%H-%M")
        self.host = host or settings.JAHIA_HOST
        self.download_params = {
            'do': 'sites',
            'exportformat': 'site',
            'sitebox': self.site,
        }

        # compute zip file name & path
        self.zip_path = zip_path or settings.JAHIA_ZIP_PATH
        self.existing_files = self.check_existing_files()
        if self.existing_files:
            self.file_path = self.existing_files[-1]
            self.file_name = os.path.basename(self.file_path)
        else:
            self.file_name = self.FILE_PATTERN % (self.site, self.date)
            self.file_path = os.path.join(self.zip_path, self.file_name)

    @property
    def file_url(self):
        return "{}://{}/{}/{}".format(settings.JAHIA_PROTOCOL, self.host, self.JAHIA_DOWNLOAD_URI, self.file_name)

    @property
    def already_downloaded(self):
        return len(self.existing_files) > 0

    def check_existing_files(self):
        path = Path(self.zip_path)
        return [str(file_path) for file_path in path.glob("%s_export*" % self.site)]
