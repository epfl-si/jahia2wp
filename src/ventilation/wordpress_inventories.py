#!/usr/bin/env python3
# -*- coding: utf-8; -*-
# All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

"""wordpress_inventories.py: Find where source Wordpresses reside from a CSV file.

The CSV file must have a 'source' column with URLs in it.  Other columns are
ignored.

Usage:
  wordpress_inventories.py --sources=<sources_inventory_file> --targets=<targets_inventory_file> <ventilation_csv_file>

"""

from docopt import docopt

import os
import sys
import re
import copy
from urllib.parse import urlparse

dirname = os.path.dirname
basename = os.path.basename
sys.path.append(dirname(dirname(os.path.realpath(__file__))))

from utils import Utils        # noqa: E402
from ops import SshRemoteHost  # noqa: E402


class AnsibleGroup:
    def __init__(self):
        self.hosts = {}

    def has_wordpress(self, designated_name):
        return designated_name in self.hosts

    def add_wordpress_by_url(self, url):
        ssh = SshRemoteHost.for_url(url)
        moniker = site_moniker(url)
        ansible_params = copy.copy(ssh.as_ansible_params(url))
        self.hosts[moniker] = ansible_params

    def save(self, target):
        group_name = basename(target)
        with open(target, 'w') as f:
            f.write("# Automatically generated by %s\n\n" % basename(__file__))
            f.write("[all-wordpresses:children]\n%s\n\n" % group_name)

            f.write("[%s]\n" % group_name)
            for host, vars in self.hosts.items():
                vars_txt = ' '.join(
                    '%s=%s' % (k, v) for (k, v) in vars.items())
                f.write("%s\t%s\n" % (host, vars_txt))

    def __repr__(self):
        return '<%s %s>' % (self.__class__, self.name)


class VentilationTodo:
    """A list of action items for ventilation, materialized as a CSV file."""

    class Item:
        """ Line of csv ventilation.csv """
        def __init__(self, line):

            source_url = line['source']

            self.source_url_full = source_url

            # all pages requested - end character * must be deleted
            if source_url.endswith("*"):
                self.one_page = False
                self.source_url = source_url.rstrip('*')

            # single page requested - source url must be URL of WP site
            else:

                self.one_page = True

                if source_url.endswith("/"):
                    source_url = source_url[0:-1]

                self.source_url = os.path.split(source_url)[0] + "/"

            self.destination_site = line['destination_site']
            self.relative_uri = line['relative_uri']

    def __init__(self, csv_path):
        self.items = [self.Item(line) for line in Utils.csv_filepath_to_dict(csv_path)]


def site_moniker(url):
    """
    Return
    A short name that identifies this URL either in a file path (under wxr-ventilated/),
    or in an Ansible hosts file.

    Example:
    url = "https://migration-wp.epfl.ch/help-actu/*"
    return "help-actu"
    """
    parsed = urlparse(url)
    hostname = parsed.netloc.split('.')[0]
    if hostname not in ('migration-wp', 'www2018'):
        return hostname
    return re.search('^/([^/]*)/', parsed.path).group(1)


if __name__ == '__main__':
    args = docopt(__doc__)
    # TODO: In test, we have only one group for all sources and for
    # all targets (same OpenShift host and WP_ENV). This may not be
    # the case in prod.
    sources = AnsibleGroup()
    targets = AnsibleGroup()
    for task in VentilationTodo(args['<ventilation_csv_file>']).items:
        sources.add_wordpress_by_url(task.source_url)
        targets.add_wordpress_by_url(task.destination_site)
    sources.save(args['--sources'])
    targets.save(args['--targets'])
