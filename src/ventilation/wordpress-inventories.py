#!/usr/bin/env python3
# -*- coding: utf-8; -*-
# All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

"""wordpress-inventories.py: Find where source Wordpresses reside from a CSV file.

The CSV file must have a 'source' column with URLs in it.  Other columns are
ignored.

Usage:
  wordpress-inventories.py --sources=<sources_inventory_file> --targets=<targets_inventory_file> <ventilation_csv_file>

"""

from docopt import docopt
from memoize import mproperty
from urllib.parse import urlparse

import os, sys
dirname = os.path.dirname
basename = os.path.basename
sys.path.append(dirname(dirname(os.path.realpath(__file__))))

from utils import Utils
from ops import SshRemoteHost


class AnsibleGroup:
    def __init__(self, common_vars):
        self.common_vars = common_vars
        self.hosts = {}

    def has_wordpress(self, designated_name):
        return designated_name in self.hosts

    def add_wordpress(self, designated_name, host_specific_vars={}):
        if self.has_wordpress(designated_name):
            raise Exception('Duplicate designated name %s' % designated_name)
        self.hosts[designated_name] = host_specific_vars

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
            for (k, v) in self.common_vars.items():
                f.write('%s: %s\n' % (k, v))

    def __repr__(self):
        return '<%s %s>' % (self.__class__, self.name)


def register_wordpress(to_ansible_group, path_or_url):
    # TODO: Again, we make a number of assumptions that only work for
    # test (i.e. that both source and target Wordpresses live under
    # /srv/int/migration-wp.epfl.ch/htdocs on the test instance). Will
    # also require rethinking for prod.
    dirname = basename(path_or_url.rstrip('/'))
    if not to_ansible_group.has_wordpress(dirname):
        to_ansible_group.add_wordpress(dirname, {'wp_path': dirname})
        

if __name__ == '__main__':
    args = docopt(__doc__)
    # TODO: In test, we have only one group for all sources and for
    # all targets (same OpenShift host and WP_ENV). This may not be
    # the case in prod.
    sources = AnsibleGroup({
        'wp_hostname': 'migration-wp.epfl.ch',
        'wp_env': 'int',
        'ansible_host': SshRemoteHost.test.host,
        'ansible_port': SshRemoteHost.test.port
    })
    targets = AnsibleGroup({
        'wp_hostname': 'migration-wp.epfl.ch',
        'wp_env': 'int',
        'ansible_host': SshRemoteHost.test.host,
        'ansible_port': SshRemoteHost.test.port
    })
    for line in Utils.csv_filepath_to_dict(args['<ventilation_csv_file>']):
        # Ignore stars for the purpose of discovering source Wordpresses:
        source = line['source'].rstrip('*')
        register_wordpress(sources,
                           SshRemoteHost.test.find_wordpress_path(source))

        target_url = line['destination_site']
        register_wordpress(targets, target_url)
    sources.save(args['--sources'])
    targets.save(args['--targets'])