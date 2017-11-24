Detailed installation process
=============================

Table of contents
-----------------

<!-- TOC -->

- [Starting point: github](#starting-point-github)
- [Initial setup (details of `make bootstrap-local`)](#initial-setup-details-of-make-bootstrap-local)
- [Your management environment (details of `make bootstrap-mgmt`)](#your-management-environment-details-of-make-bootstrap-mgmt)
- [Setting up a brand new infra (new pods, new NAS, new everything)](#setting-up-a-brand-new-infra-new-pods-new-nas-new-everything)
- [Install in C2C infra](#install-in-c2c-infra)
- [Tip to connect to C2C](#tip-to-connect-to-c2c)

<!-- /TOC -->

## Starting point: github

Github is currently the only way to leverage this project (and its awesome features).

    you@host:~$ git clone git@github.com:epfl-idevelop/jahia2wp.git
    you@host:~$ cd jahia2wp

## Initial setup (details of `make bootstrap-local`)

You have to define locally the environment variable `WP_ENV`, with the name of the environment you will use on C2C infra (or just stick with the examples and use '`your-env`' if you plan to work locally exclusively).

This variable is **really** important, since it is used by multiple scripts (make, docker-compose, python), in multiple places (local machine, container, C2C environment).

In this README file, we will use '`your-env`' as your value for `WP_ENV`.

    you@host:~$ echo "
    export WP_ENV=your-env" >> ~/.bashrc
    you@host:~$ source ~/.bashrc

Other variables will be needed at some point from your environment. You can define default values by copying and adapting the provided sample file:

    you@host:~/jahia2wp$ cp .env.sample .env

The make commands will use those values as defaults, and also pass them to docker-compose as needed. Speaking of docker, you will execute python code and tests inside a container, with local volumes. The container user (`www-data`, uid `33` in the container) will need write access on those volumes, hence you need to set some group permissions beforehand.

    you@host:~/jahia2wp$ sudo chown -R `whoami`:33 .
    you@host:~/jahia2wp$ sudo chmod -R g+w .
    you@host:~/jahia2wp$ find . -type d -exec sudo chmod g+s {} \;

Note: this part is a bit ugly on macOS since uid `33` matches the user `_appstore`.

In order to work locally, there a few pre-requisites:

1. docker and docker-compose installed (head to [INSTALL_TOOLS.md](./INSTALL_TOOLS.md) to get more details on docker setup)
1. make installed (head to [INSTALL_TOOLS.md](./INSTALL_TOOLS.md#make) to get more details on this point)

`make` and `docker` will allow to set up your containers:

    you@host:~/jahia2wp$ make up
    Creating network "local_default" with the default driver
    Pulling mgmt (camptocamp/os-wp-mgmt:latest)...
    ...
    Creating phpmyadmin ... done
    Creating mgmt ... done
    Creating db ... done
    Creating httpd ... done

You can control that everything is ok by checking that 4 containers have been started (your ids will be different):

    you@host:~/jahia2wp$ docker ps
    CONTAINER ID        IMAGE                    COMMAND                  CREATED             STATUS              PORTS                                      NAMES
    aaa                 camptocamp/os-wp-httpd   "/docker-entrypoin..."   37 seconds ago      Up 35 seconds       0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp   httpd
    bbb                 phpmyadmin/phpmyadmin    "/run.sh phpmyadmin"     39 seconds ago      Up 36 seconds       0.0.0.0:8080->80/tcp                       phpmyadmin
    ccc                 mysql:5.7                "docker-entrypoint..."   39 seconds ago      Up 37 seconds       3306/tcp                                   db
    xxx                 camptocamp/os-wp-mgmt    "/docker-entrypoin..."   39 seconds ago      Up 37 seconds       0.0.0.0:2222->22/tcp                       mgmt

And, finally, connect into the management container:

    you@host:~/jahia2wp$ make exec
    www-data@xxx:/srv/your-env$

## Your management environment (details of `make bootstrap-mgmt`)

As you do **not** want to mess furthermore with your host, we will setup the python virtual environment from the container.

However, you must respect the given `venv` directory in the example to get all the scripts working as expected:

    you@host:.../your-env$ virtualenv -p `which python3` venv
    ...
    Installing setuptools, pip, wheel...done.

If you need more details on the virtual env, have a look at [INSTALL_TOOLS.md](./INSTALL_TOOLS.md#python-virtualenv)

The alias '`vjahia2wp`' is available in the container to:

- activate this virtualenv
- set the PYTHONPATH,
- and move to the project directory

You can use it, and install the requirements:

    you@host:.../your-env$ vjahia2wp
    (venv) you@host:~/jahia2wp$ pip install -r requirements/local.txt

You can now jump to the [usage](#usage) section.

## Setting up a brand new infra (new pods, new NAS, new everything)

Connect to the infrastructure and make sure you have subfolders matching your pods. For instance:

Pods | Folder
-----|-------
httpd-manager | /srv/manager
httpd-subdomains | /srv/subdomains
httpd-www | /srv/www
httpd-intranet | /srv/intranet

Add the following files in `/srv`:
- .bashrc
- .aliases
- .config/.env 

You should derive them from an other existing infrastructure (e.g test), or from the sample files found in the repo:

- [etc/.bashrc](../etc/.bashrc)
- [etc/.aliases_c2c](../etc/.aliases_c2c)
- [.env.sample](../etc/.env.sample)

## Install in C2C infra

In order to work remotely, you need an access to C2C infra (your public SSH key needs to be authorized on the remote server).

Login to the management container (within VPN) and go to your environment:

    you@host:~$ ssh -A -o SendEnv=WP_ENV www-data@exopgesrv55.epfl.ch -p 32222
    www-data@mgmt-x-xxx:/srv/your-env$

Clone the project:

    www-data@mgmt-x-xxx:/srv/your-env$ git clone git@github.com:epfl-idevelop/jahia2wp.git
    www-data@mgmt-x-xxx:/srv/your-env$ cd jahia2wp
    www-data@mgmt-x-xxx:/srv/your-env/jahia2wp$ cp /srv/.config/.env .env

The last lines provide you with usable values for your `.env`. You still can modify them if needed:

    www-data@mgmt-x-xxx:/srv/your-env/jahia2wp$ vi .env

    # DB credentials
    MYSQL_DB_HOST=db-host
    MYSQL_SUPER_USER=db-super-user
    MYSQL_SUPER_PASSWORD=db-secret

You also have to modify the two following lines in `.env` file. If you don't do this, it won't work correctly.

    BACKUP_PATH=../data/backups
    PLUGINS_CONFIG_BASE_PATH=../data/plugins/

Nearly done. You just need to finish bootstraping, either by simply calling `make bootstrap-mgmt` or going step by step from the section above.

## Tip to connect to C2C

Set up an alias on your host:

    $ echo "
    alias managwp='echo ssh -A -o SendEnv=WP_ENV  www-data@exopgesrv55.epfl.ch -p 32222 && ssh -A -o SendEnv=WP_ENV www-data@exopgesrv55.epfl.ch -p 32222'" >> ~/.bashrc

That will allow you to connect and move to your `src` dir in two commands:

    you@host:~$ managwp
    ...
    www-data@mgmt-x-xxx:~$ vjahia2wp
    (venv) www-data@mgmt-x-xxx:/srv/your-env/jahia2wp/src$ vjahia2wp

You can now jump to the [README usage](../README.md#usage) section.
