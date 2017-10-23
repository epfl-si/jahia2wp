<!-- markdownlint-disable -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

Table of releases
-----------------

<!-- TOC depthFrom:2 depthTo:2 orderedList:false -->

- [[0.2.5] - 2017-10-20](#025---2017-10-20)
- [[0.2.4] - 2017-10-19](#024---2017-10-19)
- [[0.2.3] - 2017-10-10](#023---2017-10-10)
- [[0.2.2] - 2017-10-08](#022---2017-10-08)
- [[0.2.1] - 2017-10-05](#021---2017-10-05)
- [2017-09-20](#2017-09-20)
- [[0.1.0] - 2017-09-14](#010---2017-09-14)

<!-- /TOC -->

## [0.2.5] - 2017-10-20
**[PR #51](https://github.com/epfl-idevelop/jahia2wp/pull/51)**

**high level:**

1. Installation and activation of plugins
  - add-to-any
  - BasicAuth
  - black-studio-tinymce-widget
  - tinymce-advanced
  - epfl_infoscience
1. Configuration of add-to-any
1. Create Main menu

**low level:**

- possibilité de surcharger le port ssh du conteneur de `mgmt` avec la variable `WP_PORT_SSHD`, et celui du conteneur phpmyadmin avec `WP_PORT_PHPMA`.

## [0.2.4] - 2017-10-19
**[PR #48](https://github.com/epfl-idevelop/jahia2wp/pull/48) & [PR #50](https://github.com/epfl-idevelop/jahia2wp/pull/50)**

**high level:**
1. added command `download`
1. added vars `JAHIA_*` in  `.env.sample`, please update your `.env` file to make use of the download command
1. Ajout d'un pull des images lorsque l'on fait un 'up'
1. Ajout d'une règle 'clean' pour nettoyer les fichiers WordPress et ceux de la DB en local.

**low level:**
1. migrate crawler from jahiap
1. break code into modules `SessionHandler`, `JahiaConfig`, `JahiaCrawler`
1. support username, password, host from either .env or CLI arguments
1. added tests


## [0.2.3] - 2017-10-10
**[PR #33](https://github.com/epfl-idevelop/jahia2wp/pull/33)**

**Features deprecated in 0.2.3**
1. `check-one`, `generate-one` and `clean-one` deprecated. 
   Use `check`, `generate` and `clean` instead

**high level:**
1. added command `version`
1. added command `admins`
1. added command `inventory`
1. improved command `check-one`: actually checks that config is ok with wp-cli
1. improved command `generate-one` with parameter `--admin-password` to force password for admin instead of creating a random one
1. improved CLI messages when running jahia2wp.py script


**low level:**
1. improved commands (`run_command`, `run_mysql`, `run_wp_cli`), which actually return output, do not display `stderr`, but keep it available on error cases
1. extended `Utils` to read csv from strings and to provide `run_command`
1. improved `WPSite` with a factory method which take openshift_env and path instead of url
1. improved model `WPUser` to get role
1. improved `WPRawConfig` which now gives access to wp-config variables, and users
openshift_env and url
1. used `veritas.validators` in wordpress models
1. added deprecated decorator in utils.py

**[PR #41](https://github.com/epfl-idevelop/jahia2wp/pull/41)**

No need to rely on fixed container names

- Find containers by com.docker.compose.service label in Travis
- Find the "mgmt" container by an ad-hoc label for "make exec"
- Inter-Docker references by host name (e.g. "db") keep working,
  thanks to Docker's magic

## [0.2.2] - 2017-10-08
**[PR #40](https://github.com/epfl-idevelop/jahia2wp/pull/40)**

Organize the Makefile rules to make them more fool-proof

- Make as many targets as possible idempotent (= won't hurt if run twice)
- Split out Makefile.mgmt for targets that only make sense from inside Docker; have that file show up as "the" Makefile from the container (by way of a Docker volume)
- Make the "make bootstrap-mgmt" step implicit for the interactive use case (vjahia2wp)

## [0.2.1] - 2017-10-05
**[Commit 7c0365e](https://github.com/epfl-idevelop/jahia2wp/commit/7c0365ee6f3c7e447f29440394b42d8aa478b3cb)**

- possibilité de surcharger les variables du Makefile `WP_ENV`, `WP_PORT_HTTP` et `WP_PORT_HTTPS` par les celles d'environnement ou par la ligne de commande (par exemple `WP_PORT_HTTP=81 WP_ENV=my-env make vars`)

**[PR #22](https://github.com/epfl-idevelop/jahia2wp/pull/22)**

- création et suppression d'un Wordpress (avec le thème EPFL) par `generate-one` et `clean-one`
- création de plusieurs sites à partir d'un CSV avec `generate-many`
- consolidation du docker-compose et du Makefile pour créer un environnement local identique à l'environnement de production. Description dans le `README` et le `INSTALL_DETAILED`

## 2017-09-20
**[PR #5](https://github.com/epfl-idevelop/jahia2wp/pull/5)**

- added .env(.sample) file to define all environment vars
- updated make, docker-compose consequently
- added phpmyadmin in docker-compose
- updated `README`

## [0.1.0] - 2017-09-14

- initial revision