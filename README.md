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
    - [Assumptions](#assumptions)
    - [Requirements](#requirements)
    - [Express setup](#express-setup)
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

### Assumptions

You are a developper, with an experience of `git` and `python`.

All along this documentation the code snippets will make the assumption that you chekout the git repo into '`you@host:~$`'.

When it comes to environment, we will use the following values in our examples:

- '`your-env`' for the project environment
- '`venv`' for the python virtual environment

### Requirements

We have tried (hard) to make this process as smooth as possible, and to isolate most of the dependencies in a `docker` container. However, you still need to install a few things locally (head to [INSTALL_TOOLS.md](./docs/INSTALL_TOOLS.md) to get more details).

Be sure you meet the following requirements :

1. git
1. docker and docker-compose
1. make

Note that python is not in the requirements. You do not necessarily need it on your host since we will rely on docker's version.

### Express setup

As some commands require `sudo`, you will be asked for your system password

    you@host:~$ git clone git@github.com:epfl-idevelop/jahia2wp.git
    you@host:~$ cd jahia2wp/local
    you@host:local$ make bootstrap-local ENV=your-env
    ...
    Done with your local env. You can now
        source ~/.bashrc (to update your environment with WP_ENV value)
        make exec        (to connect into your contanier)

Simply run the commands, the `make exec` one will connect you to your container. You are now ready to jump to the next section, about [usages](#usage)

If you are looking for a more explicit process, feel free to follow the [detailed guide](./docs/INSTALL_DETAILED.md).

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
