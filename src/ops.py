#!/usr/bin/env python3
# -*- coding: utf-8; -*-
# All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

"""Model what test and prod look like on OpenShift"""

from memoize import mproperty
from urllib.parse import urlparse
import subprocess

import os


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
    def ping(self):
        self.run_ssh(['true'])
        return self  # Chainable

    @classmethod
    def _extract_hostname(cls, url):
        parsed = urlparse(url)
        return parsed.netloc

    @classmethod
    def for_url(cls, url):
        if cls._extract_hostname(url) in set((
                'migration-wp.epfl.ch',
        )):
            return cls.test.ping
        else:
            return cls.prod.ping

    def as_ansible_params(self, url):
        hostname = self._extract_hostname(url)
        if hostname == 'migration-wp.epfl.ch':
            assert self is SshRemoteHost.test
            wp_env = 'int'
            wp_hostname = 'migration-wp.epfl.ch'
        elif hostname == 'www2018.epfl.ch':
            assert self is SshRemoteHost.prod
            wp_env = 'sandbox'
            wp_hostname = hostname
        else:
            # TODO: there certainly is more to it than this.
            assert self is SshRemoteHost.prod
            wp_env = 'subdomains'
            wp_hostname = hostname

        retval = {
            'ansible_host': self.host,
            'ansible_port': self.port,
            'wp_hostname': wp_hostname,
            'wp_env': wp_env
        }

        remote_base = os.path.join('/srv', wp_env, wp_hostname, 'htdocs')
        remote_subdir = urlparse(url).path.lstrip('/')
        remote_subdir_initial = remote_subdir
        while not (self.run_ssh(
                'test -f ' + os.path.join(remote_base, remote_subdir),
                check=False)):
            if remote_subdir == '':
                raise Exception(
                    'Unable to find Wordpress for %s (started at %s/%s)',
                    remote_base, remote_subdir_initial)
            remote_subdir = os.path.dirname(remote_subdir)

        retval['wp_path'] = remote_subdir

        return retval

    def __repr__(self):
        return '<%s %s>' % (self.__class__, self.moniker)


SshRemoteHost.test = SshRemoteHost('test', host='test-ssh-wwp.epfl.ch', port=32222)
SshRemoteHost.prod = SshRemoteHost('prod', host='ssh-wwp.epfl.ch',      port=32222)
