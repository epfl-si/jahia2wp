<!-- markdownlint-disable -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

Table of releases
-----------------

<!-- TOC depthFrom:2 depthTo:2 orderedList:false -->

- [[0.2.15] - 2017-11-30](#0215---2017-11-30)
- [[0.2.14] - 2017-11-17](#0214---2017-11-17)
- [[0.2.13] - 2017-11-14/15](#0213---2017-11-1415)
- [[0.2.12] - 2017-11-09](#0212---2017-11-09)
- [[0.2.11] - 2017-11-08](#0211---2017-11-08)
- [[0.2.10] - 2017-11-08](#0210---2017-11-08)
- [[0.2.9] - 2017-11-07](#029---2017-11-07)
- [[0.2.8] - 2017-11-06](#028---2017-11-06)
- [[0.2.7] - 2017-11-01](#027---2017-11-01)
- [[0.2.6] - 2017-10-31](#026---2017-10-31)
- [[0.2.5] - 2017-10-20](#025---2017-10-20)
- [[0.2.4] - 2017-10-19](#024---2017-10-19)
- [[0.2.3] - 2017-10-10](#023---2017-10-10)
- [[0.2.2] - 2017-10-08](#022---2017-10-08)
- [[0.2.1] - 2017-10-05](#021---2017-10-05)
- [2017-09-20](#2017-09-20)
- [[0.1.0] - 2017-09-14](#010---2017-09-14)

<!-- /TOC -->

## [0.2.15] - 2017-11-30

**[PR #69](https://github.com/epfl-idevelop/jahia2wp/pull/69)**
**[PR #100](https://github.com/epfl-idevelop/jahia2wp/pull/100)**
**[PR #102](https://github.com/epfl-idevelop/jahia2wp/pull/102)**
**[PR #105](https://github.com/epfl-idevelop/jahia2wp/pull/105)**
**[PR #113](https://github.com/epfl-idevelop/jahia2wp/pull/113)**

**High level:**

1. (#69) New WP site has EPFL look and feel
1. (#69) Columns `installs_locked` and `updates_automatic` from source of trust are now taken into account
1. (#100) Ajout de 2 paramètres optionnels à `generate` pour pouvoir dire quel thème et quelle faculté de thème (couleur) on veut pour le site.
1. (#102) Suppression de la sidebar 
1. (#105) Amélioration de la sécurité selon [les recommendations de l'OWASP](https://www.owasp.org/index.php/OWASP_Wordpress_Security_Implementation_Guideline).
1. (#105) Nouveaux sites installé avec la version `latest` de WordPress au lieu de `4.8`
1. (#113) New command: `make functional-tests`, launching `test_upload.py` and `test_jahia2wp.py`

**Low level:**

1. (#69) PluginList takes into account the values from `installs_locked` and `updates_automatic`
1. (#69) If installed, those two plugins are installed in mu-plugin (in order to forbid their removal)
1. (#69) In tests, replaced epfl_infoscience.zip with redirection.zip in order to avoid conflict with mu-plugin EPFL-SC-Infoscience
1. (#100) Ajout d'un champ `theme_faculty` dans la source de vérité + gestion de celui-ci dans le code.
1. (#100) Modification du champ `theme` dans la source de vérité pour le mettre en minuscule (afin de réellement correspondre au nom du thème) + modif de la RegEx de contrôle
1. (#100) Le nom du thème par défaut a été mis dans une variable (`settings.DEFAULT_THEME_NAME`) car il commençait à se retrouver à plein d'endroits. 
1. (#100) Mise à jour des fichiers "source de vérité" pour que ça fonctionne avec le nouveau champ.
1. (#100) Modification du fichier du thème pour voir que la classe (=`theme_faculty`) est bien ajoutée à la balise `<body>` (cette modif va aussi être faite dans les fichiers du thème gérés par Aline).
1. (#102) Suppression des widgets de la sidebar de la homepage
1. (#105) Mises-à-jour automatiques du Core, des plugins et thèmes
1. (#105) Ajout du module Apache ModSecurity
1. (#105) Modification de la configuration WordPress pour empêcher l'édition en ligne des plugins et thèmes
1. (#113) automatic fetching of the IP of the httpd docker (instead of hard-code in settings.py)
1. (#113) test_uploads moved out of `jahia2wp/src/.../tests` into `jahia2wp/functional_tests`
1. (#113) new entries in makefile and makefile.mgmt to run pytest in newly created dir above
1. (#113) new var OPENSHIFT_ENV in settings.py to define which pod to use

## [0.2.14] - 2017-11-17

**[PR #77](https://github.com/epfl-idevelop/jahia2wp/pull/77)**

**High level changes:**

1. Tagged version after Sprint_S1711
1. (#77) Need to reset DB (using 5.5 instead of 5.7) and containers (images location changed)

**Low level changes:**

1. (#77) added Dockerfiles image to build up `httpd` and `mgmt` docker images
1. (#77) aligned mariaDB version (5.5) with the one used on C2C infra


## [0.2.13] - 2017-11-14/15

**[PR #70](https://github.com/epfl-idevelop/jahia2wp/pull/70)**
**[PR #75](https://github.com/epfl-idevelop/jahia2wp/pull/75)**
**[PR #76](https://github.com/epfl-idevelop/jahia2wp/pull/76)**
**[PR #78](https://github.com/epfl-idevelop/jahia2wp/pull/78)**
**[PR #80](https://github.com/epfl-idevelop/jahia2wp/pull/80)**
**[PR #81](https://github.com/epfl-idevelop/jahia2wp/pull/81)**

**High level changes:**

1. (#70) Infos de logging modifiées
1. (#70) Ajout de la gestion de "use cases" d'installation/désinstallation" de plugins spécifiques
1. (#75) added option `--force` on command `clean`
1. (#76) Nouvelle option pour lister les plugins (`list-plugins`) ajoutée dans `jahia2wp.py`. Elle permet de lister les plugins qui seront installés (ainsi que leur config si on la veut) pour un site (donné par `wp_env` et `wp_url`)
1. (#78) Added documentation and template to create cronjobs on openshift
1. (#80) added auto-configuration of polylang (with two languages: fr, en)
1. (#80) update `make vars` to display all env vars used in project
1. (#81) Cette PR ajoute un test fonctionnel à savoir : tester l'upload d'un média dans un site WP.

**Low level changes:**

1. (#70) Ajout de check si un plugin est installé ou pas avant de tenter de l'installer ou de le désinstaller.
1. (#75) removed IF EXISTS when dropping user.
1. (#75) by-passing check_config in jahia2wp.py:clean when using option --force
1. (#76) Fonction pour afficher une liste de plugins qui va être installée.
1. (#76) Pas de test spécifique ajouté pour ceci car c'est juste de l'affichage et la génération de la liste de plugins pour un site est déjà testée.
1. (#76) Modifications dans `test_jahia2wp.py` pour utiliser des variables pour les infos sur les sites à tester au lieu de hard-coder à chaque fois.
1. (#76) Modification de WPSite.name qui joue le role d'ID du site WordPress pour l'instant. Pour un sous-domaine, la valeur retournée est le sous-domaine à la place du FQDN
1. (#80) refactored code related to plugins -> new package `wordpress.plugins`
1. (#81) A noter que pour que les tests fonctionnent depuis le conteneur on utilise l'IP de Docker "172.17.0.1" qui potentiellement peut changer dans le futur.


## [0.2.12] - 2017-11-09
**[PR #64](https://github.com/epfl-idevelop/jahia2wp/pull/64)**

**High level changes:**

1. Possibilité de désinstaller un plugin (installé de base avec WordPress ou installé depuis une config générique).
1. Mise à jour de la liste des plugins (sans configuration) à installer 
1. Suppression automatique des plugins installés automatiquement avec WordPress et qui n'étaient pas dans la liste données dans l'issue #52
1. A noter que quand `simple-sitemap` est installé, la première fois qu'on retourne sur la page d'administration, on est redirigé sur la page de "démo" du plugin... pas trouvé où il était mis dans la DB qu'il fallait afficher cette page...

**Low level changes:**

1. Ajout de tests unitaires
1. Champ `action` ajouté dans le fichier de configuration YAML du plugin. Celui-ci permet de dire si on veut installer ou désinstaller un plugin. Si pas présent, c'est que le plugin doit être installé. Mise à jour du code en conséquence.
1. Correction d'un bug dans l'appel à la fonction d'extraction de la configuration d'un plugin.


## [0.2.11] - 2017-11-08
**[Issue #13](https://github.com/epfl-idevelop/jahia2wp/issues/13)**

**High level:**

1. Added miniorange generic configuration
2. Added miniorange sample specific configuration


## [0.2.10] - 2017-11-08
**[PR #67](https://github.com/epfl-idevelop/jahia2wp/pull/67)**

**High level:**

1. la commande `clean` accepte de ne pas avoir de DB et nettoie tout de même les fichiers
1. les commandes `generate` et `generate-many` acceptent des accents dans le titre Wordpress
1. `generate-many` n'affiche pas de message d'erreur si on passe le meme SCIPER_ID dans les colonnes onwer et responsible

**Low level:**

1. ajout d'un test de génération de site (avec accent et même ids) dans test_wordpress
1. utilisation de `sys.stdout.encoding `dans run_command dans la commande
1. comme c'était déjà le cas pour le output -> l'encoding est passé en kwarg de la fonction


## [0.2.9] - 2017-11-07
**[PR #66](https://github.com/epfl-idevelop/jahia2wp/pull/66)**
**[PR #65](https://github.com/epfl-idevelop/jahia2wp/pull/65)**

**High level:**

1. Installation et configuration du plugin `MainWP-Child` avec clef "secrète"
1. Added procedure in HOWTOs to explain how to update WP php files from historical repository

**Low level:**

1. Extraction de la configuration du plugin `MainWP-Child` puis modification du nécessaire pour l'ajouter à la liste des plugins à installer.
1. La clef secrète est actuellement mise dans la configuration générique du plugin.
1. On ne créé plus d'instance de `WPSite` dans `jahia2wp.py`. C'est désormais fait dans `WPPluginConfigExtractor`
1. Ajout des scripts js pour le bon fonctionnement du menu déroulant du header EPFL: epfl-idevelop/jahiap#126
1. Le fichier "modernizr.custom.js" est renommé "modernizr.js": epfl-idevelop/jahiap#279
1. J'ai enlevé l'appel de toutes les images de fond inutiles dans la feuille de style epfl.scss (les images sont remplacées par des pictos avec FontAwesome): epfl-idevelop/jahiap#279

## [0.2.8] - 2017-11-06
**[PR #55](https://github.com/epfl-idevelop/jahia2wp/pull/55)**

**Updated Theme from following bugfixes on repo jahiap:**

Id | Description
-- | --
[240](https://github.com/epfl-idevelop/jahiap/issues/240) | Texte centré en vertical sur WP mais pas sur Jahia
[129](https://github.com/epfl-idevelop/jahiap/issues/129) | Les titres sont tout en majuscule
[188](https://github.com/epfl-idevelop/jahiap/issues/188) | Mauvais format des listes à puce
[92](https://github.com/epfl-idevelop/jahiap/issues/92) | Les ligne de tableau invisible sont visibles
[85](https://github.com/epfl-idevelop/jahiap/issues/85) | Menu déroulant "personnes" en haut à droite ne déroule pas
[192](https://github.com/epfl-idevelop/jahiap/issues/192) | Affichage d'un rectangle gris à la place d'une division vide
[170](https://github.com/epfl-idevelop/jahiap/issues/170) | La boite rouge dans le corps de page ne se colle pas à droite
[169](https://github.com/epfl-idevelop/jahiap/issues/169) | Une div se colle à gauche au lieu de flotter à droite
[89](https://github.com/epfl-idevelop/jahiap/issues/89) | Séparateur gris clair en trop dans les tableaux
[239](https://github.com/epfl-idevelop/jahiap/issues/239) | couleur de background de paragraphe manquante
[225](https://github.com/epfl-idevelop/jahiap/issues/225) | Dans les paragraphes des pages, il manque le surlignage en couleur de cert[...]

## [0.2.7] - 2017-11-01
**[PR #57](https://github.com/epfl-idevelop/jahia2wp/pull/57)**

**high level:**

1. new commands `backup` and `backup-many` to backup one single site or all sites in source of trust. 

        $ python jahia2wp.py backup $WP_ENV http://localhost
        ...
        $ python jahia2wp.py backup-many path/to/csv
        ...

1. Backups are `full` by default, but can be `inc`remental by using option `--backup-type`

        $ python jahia2wp.py backup $WP_ENV http://localhost --backup-type=inc
        ...
1. new environment variable `BACKUP_PATH` to define where to store backups (by default: `jahia2wp/data/backups`)

**low level:**

A backup of a WordPress site relies on a `WPConfig`, and is called/used in a similar way as `WPGenerator`. It creates three files:
- a `.tar` of all WP files (php, assets, media)
- a `.list` reference file for incremental backups
- a `.sql` dump of the database

Backups are stored in `BACKUP_PATH` (by default: `jahia2wp/data/backups`), with the following name convention: `<wp_site_name>[_<timestamp>]_fullN[_incM].<tar|list|sql>`

**DISCLAIMER:** next iteration might revise this structure for something more easily exploitable, such as:

    backups
    ├── site_1
    │   ├── 2017-10-31
    │   │   ├── full-201710310945.tar
    │   │   ├── inc-201711012300.tar
    │   │   ├── inc-201711022301.tar
    │   │   └── inc-201711032310.tar
    │   └── 2017-11-06
    │       └── full-201711060946.tar
    ├── site_2
    │   └── 2017-11-06
    │       ├── full-201711060947.tar
    │       └── inc-201711060946.tar
    └── site_3



## [0.2.6] - 2017-10-31
**[PR #54](https://github.com/epfl-idevelop/jahia2wp/pull/54)**

**High level changes:**

1. L'extraction des informations de configuration d'un plugin est faite de manière interactive (le script se met en attente jusqu'à ce que la configuration soit faite). Une option `extract-plugin-config` a été ajoutée à `jahia2wp.py`
2. les commandes `generate` et `generate-many` utilisent les nouveaux fichiers de configuration des plugins (YAML) de manière transparente pour installer et configurer les plugins

**DISCLAIMER :** Les fichiers de configuration sont pour l'instant créés manuellement, de manière indépendante de la source de vérité. Une prochaine itération reverra ce système pour redonner la main à la source de vérité, et pouvoir générer tous les fichiers de configuration automatiquement 

**Low level changes:**

1. le fichier config.py a été éclaté en config.py, plugins.py et themes.py
1. Les classes suivantes ont été ajoutées pour la gestion des plugins (dans plugins.py):
    - `WPPluginConfigManager` fourni des accès MySQL et des informations sur la structure des tables où sont stockées les informations de configuration des plugins
    - `WPPluginConfigExtractor` (hérite de `WPPluginConfigManager`) permet d'extraire les informations de configuration d'un plugin (stockées potentiellement dans X tables différentes) et de les sauver dans un fichier YAML
    - `WPPluginConfigRestore` (hérite de `WPPluginConfigManager`) permet de restaurer les informations de configuration d'un plugin à partir d'une instance de `WPPluginConfigInfos` (décrite ci-dessous).
    - `WPPluginConfigList` fourni la liste des plugins à installer/configurer pour un site web donné par un id unique (cet ID doit encore être ajouté dans la source de vérité).
    - `WPPluginConfigInfos` permet de reconstruire les informations de configuration d'un plugin à partir d'une configuration générique à X sites et d'une potentielle configuration spécifique pour un site donné. Ces informations sont ensuite utilisées par `WPPluginConfigRestore` afin d'être mises dans la DB.
1. Du fait que l'on doit potentiellement remettre des options dans d'autres tables que la table "options" et que WPCLI ne permet que d'ajouter des informations dans cette dite-table, l'utilisation d'un package pip (`PyMYSQL`) pour faire les requêtes dans la DB a été faite. WPCLI n'est donc plus utilisé pour mettre les options dans la DB, tout est fait depuis le package.
1. Pour ce qui est de la configuration spécifique des plugins, pour le moment, seule la table `options` est gérée par le code. 
1. Les nouvelles classes créées sont utilisées pour paramétrer les plugins lors de la génération d'un site.


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