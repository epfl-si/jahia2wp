<!-- markdownlint-disable -->
<h1 align="center" style="margin:1em">
  <a href="https://jahia2wp.readthedocs.org/">
    <img src="./docs/static/jahia2wp.png"
         alt="jahia2wp"
         width="200"></a>
  <br />
  jahia2wp
</h1>

<h4 align="center">
  Control your migration from Jahia to WordPress
</h4>

<p align="center">
  <a href="https://github.com/epfl-idevelop/jahia2wp/blob/master/docs/CHANGELOG.md">
    <img src="https://img.shields.io/github/release/epfl-idevelop/jahia2wp.svg"
         alt="Changelog">
  </a>
  <a href="http://jahia2wp.readthedocs.io">
    <img src="https://img.shields.io/readthedocs/jahia2wp.svg"
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
  <a href="https://github.com/epfl-idevelop/jahia2wp/blob/master/LICENSE">
    <img src="https://img.shields.io/badge/license-MIT-blue.svg"
         alt="License" />
  </a>
</p>
<br>

## Install and Usage

Head to the [documentation](http://jahia2wp.readthedocs.io/en/master/) for next practical steps

## Roadmap and Overview

### 30/11/2017, Naked WordPress : Went Live :airplane:

We spent [6 sprints](https://github.com/epfl-idevelop/jahia2wp/projects/1?) to focus on automation and maintenance, with the objective of driving all the creation process from one shared spreadsheet (aka configuration source).

1. :balloon: installing a functional WordPress to any given URL

   1. Create a file named `generate.yaml` containing the following:<pre>
langs: en
unit_name: si-dev
unit_id: 13028
</pre>
   1. Type the following commands:

        $ export PLUGINS_CONFIG_BASE_PATH=/srv/$WP_ENV/jahia2wp/data/plugins
        $ python jahia2wp.py generate $WP_ENV http://localhost --extra-config=generate.yaml --admin-password=admin
        $ wp --path=/srv/$WP_ENV/localhost/htdocs option update plugin:epfl_tequila:has_dual_auth 1
        $ python jahia2wp.py generate $WP_ENV http://localhost/test2 --extra-config=generate.yaml --admin-password=admin
        $ wp --path=/srv/$WP_ENV/localhost/htdocs/test2 option update plugin:epfl_tequila:has_dual_auth 1
        ...

        $ python jahia2wp.py check $WP_ENV http://localhost
        WordPress site valid and accessible at http://localhost

        $ python jahia2wp.py admins $WP_ENV http://localhost
        admin:admin@example.com <administrator>
        user123456:user@epfl.ch <administrator>

💡 In lieu of the `--admin-password` flag, one can [gain access through PHPMyAdmin](https://codex.wordpress.org/Resetting_Your_Password#Through_phpMyAdmin)
💡 If you want to start over with a clean slate (e.g. after an error):

        $ rm -rf /srv/$WP_ENV/localhost/htdocs/

2. :tada: add, list plugins

        $ python jahia2wp.py extract-plugin-config $WP_ENV http://localhost output_file
        ...

        $ python jahia2wp.py list-plugins $WP_ENV http://localhost
        Plugin list for site 'localhost':
        - mainwp-child
          - action   : install
          - activated: True
          - src      : web
        - tequila
          - action   : install
          - activated: True
        ...

        $ python jahia2wp.py list-plugins $WP_ENV http://localhost --config --plugin=tequila      - tequila
        - action     : install
          - activated: True
          - src      : /srv/ebreton/jahia2wp/data/plugins/generic/tequila/v1/tequila.zip
        - tables
          + term_relationships
          + termmeta
          + terms
          + options
          + term_taxonomy
          + postmeta
        
3. :construction: maintaining the website and the plugins

        $ python jahia2wp.py version $WP_ENV http://localhost
        4.8

        $ python jahia2wp.py clean $WP_ENV http://localhost
        ...

        $ python jahia2wp.py backup $WP_ENV http://localhost
        ...

4. :champagne: applying those them functionalities to every row of our configuration source

        $ python jahia2wp.py generate-many path/to/source.csv
        ...

        $ python jahia2wp.py backup-many path/to/source.csv
        ...

        $ python jahia2wp.py inventory $WP_ENV /srv/your-env/localhost
        INFO - your-env - inventory - Building inventory...
        path;valid;url;version;db_name;db_user;admins
        ...

### Migration

1. :gift_heart: Export the content of a Jahia website as a zipped package

        $ python jahia2wp.py download dcsl --username=foo
        Jahia password for user 'foo':
        ...

1. :tent: Import parsed pages into WordPress (raw content)

        $ python jahia2wp.py export dcsl https://localhost/dcsl DCSL  --admin-password=admin

1. :tent: Support translation, hierarchy, menu, sidebar
1. Import static Jahia boxes into WordPress (shortcodes)
1. Import web-services powered Jahia boxes into WordPress (people, faq, actu, memento, infoscience, and so on ...)

### Help

Calling jahia2wp.py with `-h` will give you details on available options

    $ python jahia2wp.py -h
    ...

## Changelog

All notable changes to this project are documented in [CHANGELOG.md](./docs/CHANGELOG.md).

## Contribution

Check out [CONTRIBUTING.md](./docs/CONTRIBUTING.md) for more details

As well as our [CODE_OF_CONDUCT.md](./docs/CODE_OF_CONDUCT.md), where we pledge to making participation in our project and our community a harassment-free experience for everyone

## Contributor list

Big up to all the following people, without whom this project will not be

| [<img src="https://avatars0.githubusercontent.com/u/490665?v=4s=100" width="100px;"/><br /><sub>Manu B.</sub>](https://github.com/ebreton)<br /> | [<img src="https://avatars0.githubusercontent.com/u/2668031?v=4s=100" width="100px;"/><br /><sub>Manu J. </sub>](https://github.com/jaepetto)<br /> | [<img src="https://avatars0.githubusercontent.com/u/4997224?v=4s=100" width="100px;"/><br /><sub>Greg</sub>](https://github.com/GregLeBarbar)<br /> | [<img src="https://avatars0.githubusercontent.com/u/11942430?v=4s=100" width="100px;"/><br /><sub>Lulu</sub>](https://github.com/LuluTchab)<br /> | [<img src="https://avatars0.githubusercontent.com/u/25363740?v=4s=100" width="100px;"/><br /><sub>Laurent</sub>](https://github.com/lboatto)<br /> | [<img src="https://avatars0.githubusercontent.com/u/29034311?v=4s=100" width="100px;"/><br /><sub>Luc</sub>](https://github.com/lvenries)<br /> | <br /> |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| [<img src="https://avatars0.githubusercontent.com/u/1629585?v=4s=100" width="100px;"/><br /><sub>Dominique</sub>](https://github.com/domq)<br /> | [<img src="https://avatars0.githubusercontent.com/u/176002?v=4s=100" width="100px;"/><br /><sub>Nicolas </sub>](https://github.com/ponsfrilus)<br /> | [<img src="https://avatars0.githubusercontent.com/u/2843501?v=4s=100" width="100px;"/><br /><sub>William </sub>](https://github.com/williambelle)<br /> | [<img src="https://avatars0.githubusercontent.com/u/28109?v=4s=100" width="100px;"/><br /><sub>CampToCamp</sub>](https://github.com/camptocamp)<br /> | <br /> | <br /> | | <br /> | <br /> |
