<!-- markdownlint-disable -->
<h1 align="center" style="margin:1em">
  <a href="https://jahia2wp.readthedocs.org/">
    <img src="./docs/static/jahia2wp.png"
         alt="Markdownify"
         width="200"></a>
  <br />
  jahia2wp
</h1>

<h4 align="center">
  Control your migration from Jahia to Wordpress
</h4>

<p align="center">
  <a href="http://jahia2wp.readthedocs.io/?badge=master">
    <img src="https://readthedocs.org/projects/jahia2wp/badge/?version=master"
         alt="RDT">
  </a>
  <a href="https://travis-ci.org/epfl-idevelop/jahia2wp">
    <img src="https://travis-ci.org/epfl-idevelop/jahia2wp.svg?branch=master"
         alt="Travis">
  </a>
  <a href="https://codecov.io/gh/epfl-idevelop/jahia2wp">
    <img src="https://codecov.io/gh/epfl-idevelop/jahia2wp/branch/master/graph/badge.svg" 
         alt="Codecov" />
  </a>
</p>
<br>

Table of content
----------------

<!-- TOC -->

- [Overview](#overview)
    - [Purpose](#purpose)
    - [Roadmap](#roadmap)
- [Install](#install)
    - [Install from Github](#install-from-github)
    - [Install locally](#install-locally)
    - [Install in C2C infra](#install-in-c2c-infra)
- [Usage](#usage)
    - [Pre-requisites](#pre-requisites)
    - [Create a new wordpress site](#create-a-new-wordpress-site)
    - [Delete wordpress site](#delete-wordpress-site)
- [Contribution](#contribution)
    - [Guidelines](#guidelines)
    - [Code of Conduct](#code-of-conduct)
    - [Contributor list](#contributor-list)
- [License](#license)
- [Changelog](#changelog)

<!-- /TOC -->

## Overview

### Purpose

This repository will provide you with an amazing toolbox to migrate your old beloved Jahia website to a brand new Wordpress one.

> TODO: add animated gif of Jahia admin export ?

In the process, not only shall you **not** loose your data, but you shall also be able to control and drive the migration process, i.e

- where to migrate: URLs of your new site
- what to migrate: all pages ? groups of pages ?
- how to migrate: apply some filters to clean your HTML
- for whom to migrate: use gaspar accounts as admins

> TODO: add diagram ?

### Roadmap

We will first focus on automation and maintenance, with the objective of driving all the creation process from one shared spreadsheet (aka configuration source).

1. installing a functionnal wordpress to any given URL
1. configuring the website with supported plugins, EPFL theme
1. applying those first two steps to every row of our configuration source
1. maintening the website and the plugins

We will secondly add support for migration of simple site

1. Jahia text boxes, to wordpress pages
1. translation, hierarchy, sidebar

And lastly we will extend the support to other Jahia boxes, mainly thanks to Wordpress shortcodes

- people, faq, actu, memento, infoscience, and so on ...

## Install

### Install from Github

Github is currently the only way to go :

    $ git clone git@github.com:epfl-idevelop/jahia2wp.git
    $ cd jahia2wp

Set your variable environments, by copying and adapting the provided sample file :

    $ cp local/.env.sample local/.env

If you only work locally, all the default values should work for you: you are done and you can jump to the [usage section](#usage).

Otherwise, you want to modify a few default values : 

    $ vi local/.env

You will first adapt the value of `WP_ENV` to match the name of your environment on C2C infrastructure, as well as `MYSQL_SUPER_*` for the DB credentials :

    WP_ENV?=your-env
    MYSQL_DB_HOST?=db-host
    MYSQL_SUPER_USER?=db-super-user
    MYSQL_SUPER_PASSWORD?=db-secret

As you are sharing the host with some other contributors, you want to modify a few more defaults of environment variables :

    # still in ./local/.env ...
    WP_TITLE?=Prefixed Site Name
    WP_DB_NAME?=prefixed-db-name
    MYSQL_WP_USER?=prefixed-username

Note that you should keep the question mark in `?=`. That will allow you to override this value when calling `make`.

### Install locally

In order to work locally, there are three pre-requisites:

1. been through the [Github section](#install-from-github) above
1. docker and docker-compose installed
1. camptocamp images built locally

Head to [INSTALL_TOOLS.md](./docs/INSTALL_TOOLS.md) to get more details on docker setup.

Start db, httpd containers and run your management container :

    $ cd local
    $ make up
    Creating network "local_default" with the default driver
    ...
    Creating phpmyadmin ... done
    Creating db ... done
    Creating httpd ... done
    $ make run
    www-data@4b01c9e89705:~$

    # cd to your project directory
    www-data@4b01c9e89705:~$ gowp
    www-data@99a062ae942a:~/test/jahia2wp/local$

You can now jump to the [usage](#usage) section.

### Install in C2C infra

Login to the management container (within VPN) and go to your environment:

    $ ssh -A -o SendEnv=WP_ENV www-data@exopgesrv55.epfl.ch -p 32222
    www-data@mgmt-2-fffxv:~$ cd $WP_ENV
    www-data@mgmt-2-fffxv:~/ebreton$

> TODO: issue for C2C to AcceptEnv WP_ENV

Setup the project from github as described in [Github section](#install-from-github)

And move to your project directory

    www-data@mgmt-2-fffxv:~$ cd $WP_ENV/jahia2wp/local
    www-data@mgmt-2-fffxv:~/test/jahia2wp/local$

You can now jump to the [usage](#usage) section.

## Usage

### Pre-requisites

In this section, we assumed you have been throught all [installation steps](#install), and you now have a bash running in your management container

    # locally
    www-data@99a062ae942a:~/test/jahia2wp/local$

    # C2C infra
    www-data@mgmt-2-fffxv:~/your-env/jahia2wp/local$

The usage are independant from where you are. The same Makefile is used both locally and in C2C infra. Only the values of the variables from the .env file vary.

We will stick to the default values for the examples (which matches the locally setup with no modification)

### Create a new wordpress site

If you have been through the [usage pre-requisites](#pre-requisites). you only need to run `make install`. The default values will setup a site on localhost.

    $ make install
    creating mySQL user
    mkdir -p /srv/test/localhost/htdocs
    wp core download --version=4.8 --path=/srv/test/localhost/htdocs
    Downloading WordPress 4.8 (en_US)...
    Success: WordPress downloaded.
    wp config create --dbname=db1 --dbuser=user1 --dbpass=passw0rd --dbhost=db --path=/srv/test/localhost/htdocs
    Success: Generated 'wp-config.php' file.
    wp db create --path=/srv/test/localhost/htdocs
    Success: Database created.
    wp --allow-root core install --url=http://localhost --title="EB WP1" --admin_user=admin --admin_password=admin --admin_email=test@example.com --path=/srv/test/localhost/htdocs
    sh: 1: /usr/sbin/sendmail: not found
    Success: WordPress installed successfully.

You can check that a new Wordpress is running on [localhost](http://localhost)

<pre><code>https://env-gc-os-exopge.epfl.ch/</code></pre>

### Delete wordpress site

Onvce again, given you have been through the [usage pre-requisites](#pre-requisites), you only need to run `make clean`. The default values will dictate which site to delete (i.e localhost)

    $ make clean
    rm -rf /srv/test/localhost
    cleaning up user and DB

## Contribution

There are a few ways where you can help out:

1. Submit [Github issues](https://github.com/epfl-idevelop/jahia2wp/issues) for any feature enhancements, bugs or documentation problems.
1. Fix open issues by sending PRs (please make sure you respect [flake8](http://flake8.pycqa.org/en/latest/) conventions and that all tests pass) :

   make test

1. Add documentation (written in [markdown](https://daringfireball.net/projects/markdown/))

### Guidelines

### Code of Conduct

As detailed in [CODE_OF_CONDUCT.md](./docs/CODE_OF_CONDUCT.md), we pledge to making participation in our project and our community a harassment-free experience for everyone

### Contributor list

Big up to all the following people, without whom this project will not be

| | | |  |  |  | |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| [<img src="https://avatars0.githubusercontent.com/u/490665?v=4s=100" width="100px;"/><br /><sub>Manu B.</sub>](https://github.com/ebreton)<br /> | [<img src="https://avatars0.githubusercontent.com/u/2668031?v=4s=100" width="100px;"/><br /><sub>Manu J. </sub>](https://github.com/jaepetto)<br /> | [<img src="https://avatars0.githubusercontent.com/u/4997224?v=4s=100" width="100px;"/><br /><sub>Greg</sub>](https://github.com/GregLeBarbar)<br /> | [<img src="https://avatars0.githubusercontent.com/u/11942430?v=4s=100" width="100px;"/><br /><sub>Lulu</sub>](https://github.com/LuluTchab)<br /> | [<img src="https://avatars0.githubusercontent.com/u/25363740?v=4s=100" width="100px;"/><br /><sub>Laurent</sub>](https://github.com/lboatto)<br /> | [<img src="https://avatars0.githubusercontent.com/u/29034311?v=4s=100" width="100px;"/><br /><sub>Luc</sub>](https://github.com/lvenries)<br /> | [<img src="https://avatars0.githubusercontent.com/u/28109?v=4s=100" width="100px;"/><br /><sub>CampToCamp</sub>](https://github.com/camptocamp)<br /> | 



## License

[MIT license - Copyright (c) EPFL](./LICENSE)

## Changelog

All notable changes to this project are documented in [CHANGELOG.md](./CHANGELOG.md).
