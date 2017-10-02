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
  Control your migration from Jahia to WordPress
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
    - [Express setup (locally)](#express-setup-locally)
    - [Express setup (C2C)](#express-setup-c2c)
- [Usage](#usage)
    - [Enter the container](#enter-the-container)
    - [Testing](#testing)
    - [Create a new WordPress site on a dedicated domain](#create-a-new-wordpress-site-on-a-dedicated-domain)
    - [Create a new WordPress site in a subfolder](#create-a-new-wordpress-site-in-a-subfolder)
    - [Delete a WordPress site](#delete-wordpress-site)
    - [phpMyAdmin (locally)](#phpmyadmin-locally)
- [Contribution](#contribution)
    - [Guidelines](#guidelines)
    - [Code of Conduct](#code-of-conduct)
    - [Contributor list](#contributor-list)
- [Roadmap](#roadmap)
- [Changelog](#changelog)

<!-- /TOC -->

## Overview

This repository will provide you with an amazing toolbox to migrate your old beloved Jahia website to a brand new WordPress one.

> TODO: add animated gif of Jahia admin export ?

In the process, not only shall you **not** loose your data, but you shall also be able to control and drive the migration process, i.e:

- where to migrate: URLs of your new site
- what to migrate: all pages? groups of pages?
- how to migrate: apply some filters to clean your HTML
- for whom to migrate: use gaspar accounts as admins

> TODO: add diagram ?

## License

[MIT license - Copyright (c) EPFL](./LICENSE)

## Install

### Assumptions

You are a developer, with an experience of `git` and `python`.

In this documentation the code snippets will make the assumption that you checkout the git repo into '`you@host:~$`'.

When it comes to the environment, we will use the following values in our examples:

- '`your-env`' for the project environment
- '`venv`' for the python virtual environment

### Requirements

We have tried (hard) to make this process as smooth as possible, and to isolate most of the dependencies in a `docker` container. However, you still need to install a few things locally (head to [INSTALL_TOOLS.md](./docs/INSTALL_TOOLS.md) to get more details).

Be sure you meet the following requirements:

1. git
1. docker and docker-compose
1. make

Note that python is not in the requirements. You do not necessarily need it on your host since we will rely on docker's version.

### Express setup (locally)

![architecture locale](./docs/static/archi_local.jpg)

As some commands require `sudo`, you will be asked for your system password

    you@host:~$ git clone git@github.com:epfl-idevelop/jahia2wp.git
    you@host:~$ cd jahia2wp/local
    you@host:local$ make bootstrap-local ENV=your-env
    ...
    -> instructions to finish local setup

Simply run the instructions given in the last lines from the script.

Among them, `make exec` will connect you to your container, where you can configure it:

    you@host:local$ make exec
    www-data@xxx:/srv/your-env/jahia2wp$ cd local
    www-data@xxx:/srv/your-env/jahia2wp/local$ make bootstrap-mgmt
    ...

You are now ready to jump to the next section, about [usages](#usage).

If you are looking for a more explicit process, feel free to follow the [detailed guide](./docs/INSTALL_DETAILED.md).

### Express setup (C2C)

![architecture Infra C2C](./docs/static/archi_infra_C2C.jpg)

You will need to ask C2C to add your public key in `authorized_keys` on the server.

    you@host:~$ export WP_ENV=your-env
    you@host:~$ ssh -A -o SendEnv=WP_ENV www-data@exopgesrv55.epfl.ch -p 32222
    
    www-data@mgmt-x-xxx:/srv/your-env$ git clone git@github.com:epfl-idevelop/jahia2wp.git
    www-data@mgmt-x-xxx:/srv/your-env$ cd jahia2wp/local
    www-data@mgmt-x-xxx:/srv/your-env/jahia2wp/local$ cp /srv/.config/.env .
    www-data@mgmt-x-xxx:/srv/your-env/jahia2wp/local$ make bootstrap-mgmt
    ...

## Usage

### Enter the container

In this section, we assume that you have been through all [the installation steps](#install), and you now have a bash running in your management container:

    # locally
    you@host:local$ make exec
    www-data@xxx:/srv/your-env$

    # C2C infra
    you@host:~$ managwp
    www-data@mgmt-x-xxx:/srv/your-env$

The usage is independent from where you are. The same docker image is used in both case. The difference will come from the variables in the .env file. 

Anyway, start with this useful alias:

    www-data@...:/srv/your-env$ vjahia2wp
    (venv) www-data@...:/srv/your-env/jahia2wp/src$ 


### Testing

Either from your host:

    you@host:~/jahia2wp$ make test
    ...

Or from the management container:

    (venv) www-data@...:/srv/your-env/jahia2wp/src$ pytest
    ...

    (venv) www-data@...:/srv/your-env/jahia2wp/src$ cd .. 
    (venv) www-data@...:/srv/your-env/jahia2wp$ make test-raw
    ...

### Create a new WordPress site on a dedicated domain

If you have been through the [usage pre-requisites](#pre-requisites) you only need to run `make install`. The default values will setup a site on localhost.

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

You can check that a new WordPress is running on [localhost](http://localhost).

### Create a new WordPress site in a subfolder

Creating a WordPress site in a subfolder only requires that you set the variable WP_FOLDER in your .env file, with a relative path

    SITE_DOMAIN?=localhost
    WP_FOLDER?=folder-name


Run `make install` as above and your site will be available on [localhost/folder-name](http://localhost/folder-name)

### Delete a WordPress site

Once again, given you have been through the [usage pre-requisites](#pre-requisites), you only need to run `make clean`. The default values will dictate which site to delete (i.e localhost)

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

1. installing a functional WordPress to any given URL
1. configuring the website with supported plugins and the EPFL theme
1. applying those first two steps to every row of our configuration source
1. maintaining the website and the plugins

We will secondly add support for migration of a simple site:

1. Jahia text boxes, to WordPress pages
1. translation, hierarchy, sidebar

And lastly we will extend the support to other Jahia boxes, mainly thanks to WordPress shortcodes

- people, faq, actu, memento, infoscience, and so on ...

## Changelog

All notable changes to this project are documented in [CHANGELOG.md](./CHANGELOG.md).
