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

        group_vars_dir = os.path.join(os.path.dirname(target), 'group_vars')
        os.makedirs(group_vars_dir, exist_ok=True)
        with open(os.path.join(group_vars_dir, group_name), 'w') as f:
            f.write("# Automatically generated by %s\n\n" % basename(__file__))

    def __repr__(self):
        return '<%s %s>' % (self.__class__, self.name)


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
    for line in Utils.csv_filepath_to_dict(args['<ventilation_csv_file>']):
        # Ignore stars for the purpose of discovering source Wordpresses:
        source = line['source'].rstrip('*')
        sources.add_wordpress_by_url(source)

        target = line['destination_site']
        targets.add_wordpress_by_url(target)
    sources.save(args['--sources'])
    targets.save(args['--targets'])
