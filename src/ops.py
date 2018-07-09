#!/usr/bin/env python3
# -*- coding: utf-8; -*-
# All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

"""Model what test and prod look like on OpenShift"""

from memoize import mproperty
from urllib.parse import urlparse
import subprocess

import os
import sys
dirname = os.path.dirname
sys.path.append(dirname(dirname(__file__)))


class SshRemoteHost:
    def __init__(self, moniker, host, port):
        self.moniker = moniker
        self.host = host
        self.port = port

    def run_ssh(self, args, **kwargs):
        ssh_boilerplate = [
            'ssh', '-Aq', '-T',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=1', '-p', str(self.port), self.host]
        if isinstance(args, list):
            args = ssh_boilerplate + args
        else:
            args = ' '.join(ssh_boilerplate) + ' ' + args
            kwargs['shell'] = True
        return subprocess.run(args, stdout=subprocess.PIPE, **kwargs)

    @mproperty
    def ping(self, args):
        self.run_ssh(['true'])
        return self  # Chainable

    @classmethod
    def extract_hostname(cls, url):
        parsed = urlparse(url)
        return parsed.netloc

    @classmethod
    def for_url(cls, url):
        if cls.extract_hostname(url) in set(
                'migration-wp.epfl.ch'
        ):
            return cls.test.ping
        else:
            raise Exception('No prod access implemented for now.')

    def wp_env(self, url):
        if self.extract_hostname(url) == 'migration-wp.epfl.ch':
            return 'int'
        else:
            raise Exception('Unknown wp_env for URL %s' % url)

    def base_htdocs_dir(self, url):
        if self.extract_hostname(url) == 'migration-wp.epfl.ch':
            return '/srv/int/migration-wp.epfl.ch/htdocs'
        else:
            raise Exception('Unknown base_htdocs_dir for %s', url)

    def find_wordpress_path(self, url):
        remote_subdir = urlparse(url).path.lstrip('/')
        remote_dir = os.path.join(self.base_htdocs_dir(url), remote_subdir)
        remote_dir_initial = remote_dir
        while not (self.run_ssh('test -f %s/wp-content' % remote_dir,
                                check=False)):
            if remote_dir == '/':
                raise Exception(
                    'Unable to find Wordpress for %s (started at %s)',
                    remote_dir_initial)
            remote_dir = os.path.dirname(remote_dir)
        if remote_dir == '/':
            # Unlikely, but eh
            return '/'
        else:
            return remote_dir.rstrip('/')

    def __repr__(self):
        return '<%s %s>' % (self.__class__, self.moniker)


SshRemoteHost.test = SshRemoteHost('test', host='test-ssh-wwp.epfl.ch', port=32222)
SshRemoteHost.prod = SshRemoteHost('prod', host='ssh-wwp.epfl.ch',      port=32222)
