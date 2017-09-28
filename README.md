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
- [License](#license)
- [Install](#install)
    - [Setup from Github](#setup-from-github)
    - [Install locally](#install-locally)
    - [Install in C2C infra](#install-in-c2c-infra)
    - [Tip to connect to C2C](#tip-to-connect-to-c2c)
- [Usage](#usage)
    - [Pre-requisites](#pre-requisites)
    - [Create a new wordpress site on a dedicated domain](#create-a-new-wordpress-site-on-a-dedicated-domain)
    - [Create a new wordpress site in a subfolder](#create-a-new-wordpress-site-in-a-subfolder)
    - [Delete wordpress site](#delete-wordpress-site)
    - [phpMyAdmin (locally)](#phpmyadmin-locally)
- [Contribution](#contribution)
    - [Guidelines](#guidelines)
    - [Code of Conduct](#code-of-conduct)
    - [Contributor list](#contributor-list)
- [Roadmap](#roadmap)
- [Changelog](#changelog)

<!-- /TOC -->

## Overview

This repository will provide you with an amazing toolbox to migrate your old beloved Jahia website to a brand new Wordpress one.

> TODO: add animated gif of Jahia admin export ?

In the process, not only shall you **not** loose your data, but you shall also be able to control and drive the migration process, i.e

- where to migrate: URLs of your new site
- what to migrate: all pages ? groups of pages ?
- how to migrate: apply some filters to clean your HTML
- for whom to migrate: use gaspar accounts as admins

> TODO: add diagram ?

## License

[MIT license - Copyright (c) EPFL](./LICENSE)

## Install

### Setup from Github

Github is currently the only way to go :

    you@host:~$ git clone git@github.com:epfl-idevelop/jahia2wp.git
    you@host:~$ cd jahia2wp

Set your variable environments, by copying and adapting the provided sample file :

    you@host:~/jahia2wp$ cp local/.env.sample local/.env

If you only work locally, all the default values should work for you. You can proceed with installing the dependencies (you might want to setup a virtual environment first, as described in [INSTALL_TOOLS.md](./docs/INSTALL_TOOLS.md#python-virtualenv)) :

    you@host:~/jahia2wp$ pip install -r requirements/local.txt

In order to work locally, you can skill the next few lines, and jump to the [next section](#install-locally).

Otherwise (i.e if you work on C2C infra), you want to modify a few default values : 

    you@host:~/jahia2wp$ vi local/.env

You first need to define locally your environment variable `WP_ENV`, with the name of the environment you will use on C2C infra

    $ echo "
    export WP_ENV=your-env" >> ~/.bashrc

If you do not want to mess with your .bashrc, you can also set this variable in your .env file, along with a few other values that will be used for all WordPresses :

    # Environment name
    WP_ENV?=your-env

    # DB credentials
    MYSQL_DB_HOST?=db-host
    MYSQL_SUPER_USER?=db-super-user
    MYSQL_SUPER_PASSWORD?=db-secret
    
Note that you should keep the question mark in `?=`. That will allow you to override this value when calling `make`.

### Install locally

In order to work locally, there a few pre-requisites:

1. been through the [Github section](#install-from-github) above
1. docker and docker-compose installed (head to [INSTALL_TOOLS.md](./docs/INSTALL_TOOLS.md) to get more details on docker setup.)
1. make installed (head to [INSTALL_TOOLS.md](./docs/INSTALL_TOOLS.md#make) to get more details on this point.)

Start the `db`, `httpd` and `mgmt` containers:

    you@host:~/jahia2wp$ cd local
    you@host:~/jahia2wp/local$ make up
    Creating network "local_default" with the default driver
    Pulling mgmt (camptocamp/os-wp-mgmt:latest)...
    ...
    Creating phpmyadmin ... done
    Creating mgmt ... done
    Creating db ... done
    Creating httpd ... done

You can control that everything went ok by checking that 4 containers have been started (your ids will be different)

    you@host:~/jahia2wp/local$ docker ps
    CONTAINER ID        IMAGE                    COMMAND                  CREATED             STATUS              PORTS                                      NAMES
    aaa                 camptocamp/os-wp-httpd   "/docker-entrypoin..."   37 seconds ago      Up 35 seconds       0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp   httpd
    bbb                 phpmyadmin/phpmyadmin    "/run.sh phpmyadmin"     39 seconds ago      Up 36 seconds       0.0.0.0:8080->80/tcp                       phpmyadmin
    ccc                 mysql:5.7                "docker-entrypoint..."   39 seconds ago      Up 37 seconds       3306/tcp                                   db
    xxx                 camptocamp/os-wp-mgmt    "/docker-entrypoin..."   39 seconds ago      Up 37 seconds       0.0.0.0:2222->22/tcp                       mgmt

From here, one command will connect you inside the mgmt container, in your-env

    you@host:~/jahia2wp/local$ make exec
    www-data@xxx:/srv/your-env$ gowp
    www-data@xxx:/srv/your-env/jahia2wp/local$

You can now jump to the [usage](#usage) section.

### Install in C2C infra

In order to work locally, there are two pre-requisites:

1. have an access to C2C infra (your public SSH key needs to be authorized on the remote server)
1. been through the [Github section](#install-from-github) above

Login to the management container (within VPN) and go to your environment:

    you@host:~$ ssh -A -o SendEnv=WP_ENV www-data@exopgesrv55.epfl.ch -p 32222
    www-data@mgmt-x-xxx:~$ cd /srv/$WP_ENV
    www-data@mgmt-x-xxx:/srv/your-env$

Setup the project from github as described in [Github section](#install-from-github)

And move to your project directory

    www-data@mgmt-x-xxx:where-ever-you-are$ gowp
    www-data@mgmt-x-xxx:/srv/your-env/jahia2wp/local$

### Tip to connect to C2C

Set up an alias on your host:

    $ echo "
    alias managwp='echo ssh -A -o SendEnv=WP_ENV  www-data@exopgesrv55.epfl.ch -p 32222 && ssh -A -o SendEnv=WP_ENV www-data@exopgesrv55.epfl.ch -p 32222'" >> ~/.bashrc

That will allow you to connect and move to your local dir in two commands:

    you@host:~$ managwp
    ...
    www-data@mgmt-x-xxx:~$ gowp

You can now jump to the [usage](#usage) section.

## Usage

### Pre-requisites

In this section, we assumed you have been throught all [installation steps](#install), and you now have a bash running in your management container:

    # locally
    www-data@xxx:~/test/jahia2wp/local$

    # C2C infra
    www-data@mgmt-x-xxx:/srv/your-env/jahia2wp/local$

The usage are independant from where you are. The same Makefile is used both locally and in C2C infra. Only the values of the variables from the .env file vary.

We will stick to the default values for the examples (which matches the locally setup with no modification)

### Create a new wordpress site on a dedicated domain

If you have been through the [usage pre-requisites](#pre-requisites). you only need to run `make install`. The default values will setup a site on localhost.

    .../local$ make install
    creating mySQL user *user1*
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

### Create a new wordpress site in a subfolder

Creating a WordPress site in a subfolder only requires that you set the variable WP_FOLDER in your .env file, with a relative path

    SITE_DOMAIN?=localhost
    WP_FOLDER?=folder-name


Run `make install` as above and your site will be available on [localhost/folder-name](http://localhost/folder-name)

### Delete wordpress site

Onvce again, given you have been through the [usage pre-requisites](#pre-requisites), you only need to run `make clean`. The default values will dictate which site to delete (i.e localhost)

    .../local$ make clean
    rm -rf /srv/test/localhost
    cleaning up user *user1* and DB *db1*

### phpMyAdmin (locally)

A phpMyAdmin is available locally at [localhost:8080](http://localhost:8080), with the server and credentials defined in your .env file

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

## Roadmap

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

## Changelog

All notable changes to this project are documented in [CHANGELOG.md](./CHANGELOG.md).
