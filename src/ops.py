#!/usr/bin/env python3
# -*- coding: utf-8; -*-
# All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017

"""Model what test and prod look like on OpenShift"""
import logging

import re
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
        self.site_name = hostname.replace(".epfl.ch", "")

        parent_host = SshRemoteHost.for_host(hostname)
        self.parent_host = parent_host
        if hostname == 'migration-wp.epfl.ch':
            assert parent_host is SshRemoteHost.test
            wp_env = 'int'
        elif hostname == 'www.epfl.ch':
            assert parent_host is SshRemoteHost.prod
            wp_env = 'www'
        elif hostname == 'archive-wp.epfl.ch':
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

    def _run_ssh(self, remote_cmd, success_msg=""):
        result = ""
        ssh = self.parent_host.run_ssh(remote_cmd, check=False)
        if ssh.returncode == 0:
            logging.debug(success_msg)
            result = ssh.stdout
        elif ssh.returncode == 1:
            logging.warning(ssh.stderr)
        elif ssh.returncode != 1:
            logging.error(ssh.stderr)
        return result

    def is_valid(self):

        wp_config_path = os.path.join(self.get_root_dir_path(), 'wp-config.php')
        remote_cmd = "ls {}".format(wp_config_path)
        ssh = self.parent_host.run_ssh(remote_cmd, check=False)

        if ssh.returncode == 0:
            logging.debug("WP site is valid")
            return True

        elif ssh.returncode != 1:
            logging.error(ssh.stderr)
            return False

    def get_root_dir_path(self):
        """
        Return the root dir path
        """
        return os.path.join('/srv', self.wp_env, self.wp_hostname, 'htdocs', self.wp_path)

    def get_htaccess_file_path(self, bak_file=False):
        """
        Return the htaccess file path
        """
        path = os.path.join(self.get_root_dir_path(), '.htaccess')

        if bak_file:

            remote_cmd = "ls -la {}/.htaccess.bak*".format(self.get_root_dir_path())
            ssh = self.parent_host.run_ssh(remote_cmd, check=False)
            if ssh.returncode == 0:
                file_name = ssh.stdout.decode("utf-8")
                file_name = ".bak" + file_name.split(".htaccess.bak")[1].replace("\n", "")
            elif ssh.returncode != 1:
                logging.error(ssh.stderr)

            path += file_name

        return path

    def get_directory_path_contains(self, file_name):
        """
        Return the path of the upload directory which contains file 'filename'
        """
        directory_path = ""
        upload_dir = os.path.join(self.get_root_dir_path(), 'wp-content/uploads/2018')

        remote_cmd = "find {} -name {}".format(upload_dir, file_name)
        ssh = self.parent_host.run_ssh(remote_cmd, check=False)
        if ssh.returncode == 0:
            directory_path = ssh.stdout.decode("utf-8")
            if directory_path.endswith("\n"):
                directory_path = directory_path[:-1]
            logging.debug("File {} found in {}".format(file_name, directory_path))

        elif ssh.returncode != 1:
            logging.error(ssh.stderr)
        return directory_path

    def get_first_file_name(self, month_upload_dir):
        """
        Return the file name of the first file of directory 'month_upload_dir'
        """
        first_file_name = ""
        remote_cmd = "ls {} | sort -n | head -1".format(month_upload_dir)
        ssh = self.parent_host.run_ssh(remote_cmd, check=False)
        if ssh.returncode == 0:
            first_file_name = ssh.stdout.decode("utf-8")
            if first_file_name.endswith("\n"):
                first_file_name = first_file_name[:-1]
            logging.debug("First file name of {} is {}".format(month_upload_dir, first_file_name))

        elif ssh.returncode != 1:
            logging.error(ssh.stderr)

        return first_file_name

    def create_htaccess_backup(self):
        """
        Create htaccess backup.
        Example of backup file: .htaccess.bak.2018-12-03T17:14:29
        """
        htaccess_file = self.get_htaccess_file_path()
        remote_cmd = 'cp {}'.format(htaccess_file) + '{,.bak."$(date +%Y-%m-%dT%H:%M:%S)"}'
        ssh = self.parent_host.run_ssh(remote_cmd, check=False)

        if ssh.returncode == 0:
            logging.debug("backup file .htaccess.bak.timestamp created")
            return True

        elif ssh.returncode != 1:
            logging.error(ssh.stderr)
            return False

    def wp_cli(self, remote_cmd):

        return self._run_ssh(remote_cmd)

    def write_htaccess_content(self, content):
        """
        Write content in htaccess file
        """
        htaccess_file_content = ""
        htaccess_file = self.get_htaccess_file_path()

        remote_cmd = "'echo -e \"{}\" > {}'".format(content, htaccess_file)
        ssh = self.parent_host.run_ssh(remote_cmd, check=False)

        if ssh.returncode == 0:
            htaccess_file_content = self.get_htaccess_content()
            logging.debug("htaccess content {} after update:\n{}".format(htaccess_file, htaccess_file_content))

        elif ssh.returncode != 1:
            logging.error(ssh.stderr)

        return htaccess_file_content

    def get_htaccess_content(self, bak_file = False):
        """
        Return content of htaccess file
        """
        htaccess_file_content = ""

        htaccess_file = self.get_htaccess_file_path(bak_file)

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

    def archive_wp_site(self):
        """
        To archive a WordPress site:
        1. mv /srv/subdomains/dcsl.epfl.ch/htdocs /srv/sandox/archive-wp.epfl.ch/htdocs/dcsl
        2. mkdir /srv/subdomains/dcsl.epfl.ch/htdocs
        3. cp /srv/sandox/archive-wp.epfl.ch/htdocs/dcsl/.htaccess /srv/subdomains/dcsl.epfl.ch/htdocs/
        """
        archive_directory = "/srv/sandbox/archive-wp.epfl.ch/htdocs/{}".format(self.site_name)
        archive_site_url = "https://archive-wp.epfl.ch/{}".format(self.site_name)

        # 1. mv /srv/subdomains/dcsl.epfl.ch/htdocs /srv/sandox/archive-wp.epfl.ch/htdocs/dcsl
        remote_cmd = "mv {} {}".format(
            self.get_root_dir_path(),
            archive_directory
        )
        success_msg = "Command {} executed with success".format(remote_cmd)
        # TODO uncomment this line below
        self._run_ssh(remote_cmd, success_msg=success_msg)

        # 2. mkdir /srv/subdomains/dcsl.epfl.ch/htdocs
        remote_cmd = "mkdir {}".format(self.get_root_dir_path())
        success_msg = "Command {} executed with success".format(remote_cmd)
        # TODO uncomment this line below
        self._run_ssh(remote_cmd, success_msg=success_msg)

        # 3. cp /srv/sandox/archive-wp.epfl.ch/htdocs/dcsl/.htaccess /srv/subdomains/dcsl.epfl.ch/htdocs/
        remote_cmd = "cp {}/.htaccess {}".format(archive_directory, self.get_root_dir_path())
        success_msg = "Command {} executed with success".format(remote_cmd)
        # TODO uncomment this line below
        self._run_ssh(remote_cmd, success_msg=success_msg)

        return archive_site_url


SshRemoteHost.test = SshRemoteHost('test', host='test-ssh-wwp.epfl.ch', port=32222)
SshRemoteHost.prod = SshRemoteHost('prod', host='ssh-wwp.epfl.ch',      port=32222)
