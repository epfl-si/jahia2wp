#!/usr/bin/env python3
# -*- coding: utf-8; -*-
# All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

"""Model what test and prod look like on OpenShift"""
import logging
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
            'ssh', '-A', '-T',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=1', '-p', str(self.port), 'www-data@' + self.host]
        if isinstance(args, list):
            args = ssh_boilerplate + args
        else:
            args = ' '.join(ssh_boilerplate) + ' ' + args
            kwargs['shell'] = True
        return subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.PIPE, **kwargs)

    @mproperty
    def ping(self):
        self.run_ssh(['true'])
        return self  # Chainable

    @classmethod
    def for_host(cls, host):
        if host in set((
                'migration-wp.epfl.ch',
        )):
            return cls.test.ping
        else:
            return cls.prod.ping

    def __repr__(self):
        return '<%s %s>' % (self.__class__, self.moniker)


class SshRemoteSite:
    """
    A Wordpress site reachable over ssh.

    Attributes:
       host          The host to ssh into
       port          The port to ssh at
       wp_env        The subdirectory of /srv that that site resides at
       wp_hostname   The name of the directory that comes just after /srv/{wp_env}
       wp_path       The path, relative to /srv/{wp_env}/{wp_hostname}/htdocs/, that this site resides at
    """
    def __init__(self, url, discover_site_path=False):
        hostname = urlparse(url).netloc
        self.wp_hostname = hostname
        parent_host = SshRemoteHost.for_host(hostname)
        self.parent_host = parent_host
        if hostname == 'migration-wp.epfl.ch':
            assert parent_host is SshRemoteHost.test
            wp_env = 'int'
        elif hostname == 'www2018.epfl.ch':
            assert parent_host is SshRemoteHost.prod
            wp_env = 'sandbox'
        else:
            # TODO: there certainly is more to it than this.
            assert parent_host is SshRemoteHost.prod
            wp_env = 'subdomains'

        self.host = parent_host.host
        self.port = parent_host.port
        self.wp_env = wp_env
        remote_subdir = urlparse(url).path.lstrip('/')

        if discover_site_path:
            remote_subdir = self._discover_site_top_dir(parent_host, remote_subdir)
        self.wp_path = remote_subdir

    def _discover_site_top_dir(self, host, remote_subdir):
        """Explore the remote filesystem and find the root of the site for `url`

        Return
           A prefix of `url`
        """
        remote_base = os.path.join('/srv', self.wp_env,
                                   self.wp_hostname, 'htdocs')
        remote_subdir_initial = remote_subdir
        while True:
            remote_cmd = 'test -d ' + os.path.join(remote_base, remote_subdir, 'wp-admin')
            ssh = host.run_ssh(remote_cmd, check=False)
            if ssh.returncode == 0:
                return remote_subdir
            elif ssh.returncode != 1:
                logging.error(ssh.stderr)
                raise Exception("Unable to connect ({}): {}".format(remote_cmd, ssh.stderr))
            if remote_subdir == '':
                raise Exception(
                    'Unable to find Wordpress for %s (started at %s/%s)' %
                    (self.moniker, remote_base, remote_subdir_initial))
            remote_subdir = os.path.dirname(remote_subdir)

    def write_htaccess_content(self, content):
        htaccess_file_content = ""
        htaccess_file = os.path.join('/srv',
                                     self.wp_env,
                                     self.wp_hostname,
                                     'htdocs',
                                     self.wp_path,
                                     '.htaccess')

        remote_cmd = "'echo -e \"{}\" > {}'".format(content, htaccess_file)
        ssh = self.parent_host.run_ssh(remote_cmd, check=False)

        if ssh.returncode == 0:
            htaccess_file_content = self.get_htaccess_content()
            logging.debug("htaccess content {} after update:\n{}".format(htaccess_file, htaccess_file_content))

        elif ssh.returncode != 1:
            logging.error(ssh.stderr)

        return htaccess_file_content

    def get_htaccess_content(self):
        """
        :return: htaccess content file
        """
        htaccess_file_content = ""
        htaccess_file = os.path.join('/srv',
                                     self.wp_env,
                                     self.wp_hostname,
                                     'htdocs',
                                     self.wp_path,
                                     '.htaccess')
        remote_cmd = "cat {}".format(htaccess_file)
        ssh = self.parent_host.run_ssh(remote_cmd, check=False)

        if ssh.returncode == 0:
            htaccess_file_content = ssh.stdout
            htaccess_file_content = htaccess_file_content.decode("utf-8")
            logging.debug("htaccess content of {}:\n{}".format(htaccess_file, htaccess_file_content))

        elif ssh.returncode != 1:
            logging.error(ssh.stderr)

        return htaccess_file_content

    def get_url(self):
        return 'https://{}/{}'.format(self.wp_hostname, self.wp_path)


SshRemoteHost.test = SshRemoteHost('test', host='test-ssh-wwp.epfl.ch', port=32222)
SshRemoteHost.prod = SshRemoteHost('prod', host='ssh-wwp.epfl.ch',      port=32222)
