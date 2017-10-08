<!-- markdownlint-disable -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

Table of releases
-----------------

<!-- TOC depthFrom:2 depthTo:2 orderedList:false -->

- [[0.2.2] - 2017-10-08](#022---2017-10-08)
- [[0.2.1] - 2017-10-05](#021---2017-10-05)
- [[Unversioned] - 2017-10-05](#unversioned---2017-10-05)
- [[Unreleased]](#unreleased)
- [[0.1.0] - 2017-09-14](#010---2017-09-14)

<!-- /TOC -->

## [0.2.2] - 2017-10-08
**[PR #40](https://github.com/epfl-idevelop/jahia2wp/pull/40)**

Organize the Makefile rules to make them more fool-proof

- Make as many targets as possible idempotent (= won't hurt if run twice)
- Split out Makefile.mgmt for targets that only make sense from inside Docker; have that file show up as "the" Makefile from the container (by way of a Docker volume)
- Make the "make bootstrap-mgmt" step implicit for the interactive use case (vjahia2wp)

## [0.2.1] - 2017-10-05
**[Commit 7c0365e](https://github.com/epfl-idevelop/jahia2wp/commit/7c0365ee6f3c7e447f29440394b42d8aa478b3cb)**

- possibilité de surcharger les variables du Makefile `WP_ENV`, `WP_PORT_HTTP` et `WP_PORT_HTTPS` par les celles d'environnement ou par la ligne de commande (par exemple `WP_PORT_HTTP=81 WP_ENV=my-env make vars`)

## [Unversioned] - 2017-10-05
**[PR #22](https://github.com/epfl-idevelop/jahia2wp/pull/22)**

- création et suppression d'un Wordpress (avec le thème EPFL) par `generate-one` et `clean-one`
- création de plusieurs sites à partir d'un CSV avec `generate-many`
- consolidation du docker-compose et du Makefile pour créer un environnement local identique à l'environnement de production. Description dans le `README` et le `INSTALL_DETAILED`

## [Unreleased]
**[PR #5](https://github.com/epfl-idevelop/jahia2wp/pull/5)**

- added .env(.sample) file to define all environment vars
- updated make, docker-compose consequently
- added phpmyadmin in docker-compose
- updated `README`

## [0.1.0] - 2017-09-14

- initial revision