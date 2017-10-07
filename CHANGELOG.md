<!-- markdownlint-disable -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

Table of releases
-----------------

<!-- TOC depthFrom:2 depthTo:2 orderedList:false -->

- [[0.2.2] - 2017-10-09](#022---2017-10-09)
- [[0.2.1] - 2017-10-05](#021---2017-10-05)
- [[Unversioned] - 2017-10-05](#unversioned---2017-10-05)
- [[Unreleased]](#unreleased)
- [[0.1.0] - 2017-09-14](#010---2017-09-14)

<!-- /TOC -->

## [0.2.2] - 2017-10-09
**[PR #33](https://github.com/epfl-idevelop/jahia2wp/pull/33)**

**high level:**
1. added command `wp-version`
1. added command `wp-admins`
1. improved command `check-one`: actually checks that config is ok with wp-cli
1. improved command `generate-one` with parameter `--admin-password` to force password for admin instead of creating a random one

**low level:**
1. improved commands (`run_command`, `run_mysql`, `run_wp_cli`), which actually return output, do not display `stderr`, but keep it available on error cases
1. improved `WPRawConfig` which now gives access to wp-config variables, and users
1. used `veritas.validators` in wordpress models
1. improved model `WPUser` to get role
1. extended `Utils` to read csv from strings. (added tests)

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