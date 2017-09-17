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
    - [Configuration des variables d'environnements](#configuration-des-variables-denvironnements)
    - [Modification des variables d'environnements](#modification-des-variables-denvironnements)
    - [Activation des variables d'environnements](#activation-des-variables-denvironnements)
- [Usage](#usage)
    - [Lancer le make install](#lancer-le-make-install)
    - [Lancer le make clean](#lancer-le-make-clean)
- [Contribution](#contribution)
    - [Guidelines](#guidelines)
    - [Code of Conduct](#code-of-conduct)
    - [Contributor list](#contributor-list)
- [License](#license)
- [Changelog](#changelog)

<!-- /TOC -->

## Overview

### Purpose

### Roadmap

## Install

On se connecte à la machine :

<pre><code>ssh www-data@exopgesrv55.epfl.ch -p 32222</code></pre>


<blockquote style="background-color: red; color: white"; font-weight: bold;>
<p>Attention ! Vous devez adapter les commandes qui suivent avec les informations propres à votre environnement:</p>
</blockquote>

- https://env-ej-os-exopge.epfl.ch -> /srv/ejaep
- https://env-lv-os-exopge.epfl.ch  -> /srv/lvenries
- https://env-lc-os-exopge.epfl.ch  -> /srv/lchaboudez
- https://env-lb-os-exopge.epfl.ch  -> /srv/lboatto
- https://env-gc-os-exopge.epfl.ch  -> /srv/gcharmier
- https://env-eb-os-exopge.epfl.ch  -> /srv/ebreton

### Configuration des variables d'environnements #

Dans le répertoire <code>/srv/jahia2wp/etc/</code>, il existe 2 fichiers <code>.env</code>

* db.env
* wp.env

On commence par copier ce répertoire dans notre espace personnel. 

<pre><code>cp -r /srv/jahia2wp/etc/ /srv/gcharmier/</code></pre>

### Modification des variables d'environnements

On adapte les variables d'environnement pour notre espace de développement

Pour le fichier <code>/srv/gcharmier/etc/wp.env</code>

<pre><code>WP_PATH=/srv/gcharmier/env-gc-os-exopge.epfl.ch</code></pre>

<pre><code>WP_DB_NAME=gcwp1</code></pre>

<pre><code>WP_URL=https://env-gc-os-exopge.epfl.ch</code></pre>

<pre><code>WP_TITLE=GCWP1</code></pre>
  
Pour le fichier <code>/srv/gcharmier/etc/db.env</code>

<pre><code>MYSQL_WP_USER=ugcwp1</code></pre>

### Activation des variables d'environnements

On active les modifications des variables d'environnements :

<pre><code>source /srv/gcharmier/etc/wp.env</code></pre>

<pre><code>source /srv/gcharmier/etc/db.env</code></pre>


## Usage

### Lancer le make install #

Se positionner dans le root du projet contenant le Makefile :
<pre><code>cd /srv/jahia2wp/</code></pre>

On lance l'installation du site WordPress

<pre><code>make install</code></pre>

On peut vérifier que le site répond :

<pre><code>https://env-gc-os-exopge.epfl.ch/</code></pre>

### Lancer le make clean

Se positionner dans le root du projet contenant le Makefile :
<pre><code>cd /srv/jahia2wp/</code></pre>

On peut supprimer les actions de la commande make install via :
<pre><code>make clean</code></pre>


## Contribution

There are a few ways where you can help out:

1. Submit [Github issues](https://github.com/epfl-idevelop/jahia2wp/issues) for any feature enhancements, bugs or documentation problems.
1. Fix open issues by sending PRs (please make sure you respect [flake8](http://flake8.pycqa.org/en/latest/) conventions and that all tests pass) ::

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
