Table of content
----------------

<!-- TOC -->

- [Initial setup](#initial-setup)
- [Install locally](#install-locally)
- [Install in C2C infra](#install-in-c2c-infra)
- [Tip to connect to C2C](#tip-to-connect-to-c2c)

<!-- /TOC -->

## Initial setup

Github is currently the only way to leverage this project (and its awesome features). 

    you@host:~$ git clone git@github.com:epfl-idevelop/jahia2wp.git
    you@host:~$ cd jahia2wp

You also have to define locally the environment variable `WP_ENV`, with the name of the environment you will use on C2C infra (or use '`test`' if you plan to work locally exclusively).

This variable is **really** important, since it is used by multiple scripts (make, docker-compose, python), in multiple place (local machine, container, C2C environment).

In this README file, we will use '`your-env`' as your value for `WP_ENV`.

    you@host:~$ echo "
    export WP_ENV=your-env" >> ~/.bashrc
    you@host:~$ source ~/.bashrc

Other variables will be needed at some point from your environment. You can define default values by copying and adapting the provided sample file :

    you@host:~/jahia2wp$ cp local/.env.sample local/.env

The make commands will use those values as defaults, and also pass them to docker-compose as needed. Speaking of docker, you wille execute python code and tests inside a container, with local volumes. The container user (`www-data`, uid `33` in the container) will need write access on those volumes, hence you need to set some group permissions beforehand.

    you@host:~/jahia2wp$ sudo chown -R `whoami`:33 .
    you@host:~/jahia2wp$ sudo chmod -R g+w .
    you@host:~/jahia2wp$ find . -type d -exec sudo chmod g+s {} \;

Note: this part is a bit ugly on mac Os X since uid `33` matches the user `_appstore`

## Install locally

In order to work locally, there a few pre-requisites:

1. docker and docker-compose installed (head to [INSTALL_TOOLS.md](./INSTALL_TOOLS.md) to get more details on docker setup.)
1. make installed (head to [INSTALL_TOOLS.md](./INSTALL_TOOLS.md#make) to get more details on this point.)

As you do **not** want to mess up your host, set up a virtual environment. You muse use the path '`jahia2wp/data/srv/your-env`' as root directory, and '`venv`' as directory name because you will also make use of it in your container. Hence the commands:

    you@host:~/jahia2wp$ mkdir -p data/srv/your-env
    you@host:~/jahia2wp$ cd data/srv/your-env
    you@host:.../your-env$ virtualenv -p `which python3` venv
    ...
    Installing setuptools, pip, wheel...done.
    you@host:.../your-env$ source venv/bin/activate

If you need more details on the virtual env, have a look at [INSTALL_TOOLS.md](./INSTALL_TOOLS.md#python-virtualenv)

The alias '`vjahia2wp`' will be available in the container to:
- activate this virtualenv
- set the pythonpath,
- and move to the project directory.

You probably want to also set it in your .bashrc file to align the behavior on your local machine and in the container. (adapt the path ~/jahia2wp to the real path where you have cloned jahia2wp)

    you@host:~/jahia2wp$ echo "
    alias vjahia2wp=source ~/jahia2wp/data/srv/${WP_ENV}/venv/bin/activate && export PYTHONPATH=~/jahia2wp/src && cd ~/jahia2wp " >> ~/.bashrc
    you@host:~/jahia2wp$ source ~/.bashrc

You can now call it, and finally install the requirements

    you@host:.../your-env$ vjahia2wp
    (venv) you@host:~/jahia2wp$ pip install -r requirements/local.txt

You are now set! Just go to the `local` dir to start your docker containers, and login into your mgmt container.

    (venv) you@host:~/jahia2wp$ cd local
    (venv) you@host:~/jahia2wp/local$ make up
    Creating network "local_default" with the default driver
    Pulling mgmt (camptocamp/os-wp-mgmt:latest)...
    ...
    Creating phpmyadmin ... done
    Creating mgmt ... done
    Creating db ... done
    Creating httpd ... done

You can control that everything went ok by checking that 4 containers have been started (your ids will be different)

    (venv) you@host:~/jahia2wp/local$ docker ps
    CONTAINER ID        IMAGE                    COMMAND                  CREATED             STATUS              PORTS                                      NAMES
    aaa                 camptocamp/os-wp-httpd   "/docker-entrypoin..."   37 seconds ago      Up 35 seconds       0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp   httpd
    bbb                 phpmyadmin/phpmyadmin    "/run.sh phpmyadmin"     39 seconds ago      Up 36 seconds       0.0.0.0:8080->80/tcp                       phpmyadmin
    ccc                 mysql:5.7                "docker-entrypoint..."   39 seconds ago      Up 37 seconds       3306/tcp                                   db
    xxx                 camptocamp/os-wp-mgmt    "/docker-entrypoin..."   39 seconds ago      Up 37 seconds       0.0.0.0:2222->22/tcp                       mgmt

From here, one command will connect you inside the mgmt container, in your-env

    (venv) you@host:~/jahia2wp/local$ make exec
    www-data@xxx:/srv/your-env$ vjahia2wp
    (venv) www-data@xxx:/srv/your-env/jahia2wp$

You can now jump to the [usage](#usage) section.

## Install in C2C infra

In order to work remotely, you need an access to C2C infra (your public SSH key needs to be authorized on the remote server)

Login to the management container (within VPN) and go to your environment:

    you@host:~$ ssh -A -o SendEnv=WP_ENV www-data@exopgesrv55.epfl.ch -p 32222
    www-data@mgmt-x-xxx:~$ cd /srv/$WP_ENV
    www-data@mgmt-x-xxx:/srv/your-env$

Setup the project as described in the first [initial setup](#initial-setup)

You want to modify a few default values to be used by the containers: 

    you@host:~/jahia2wp$ vi local/.env

    # DB credentials
    MYSQL_DB_HOST?=db-host
    MYSQL_SUPER_USER?=db-super-user
    MYSQL_SUPER_PASSWORD?=db-secret
    
Note that you should keep the question mark in `?=`. That will allow you to override this value when calling `make`.

Move to your project directory

    www-data@mgmt-x-xxx:where-ever-you-are$ gowp
    www-data@mgmt-x-xxx:/srv/your-env/jahia2wp$

## Tip to connect to C2C

Set up an alias on your host:

    $ echo "
    alias managwp='echo ssh -A -o SendEnv=WP_ENV  www-data@exopgesrv55.epfl.ch -p 32222 && ssh -A -o SendEnv=WP_ENV www-data@exopgesrv55.epfl.ch -p 32222'" >> ~/.bashrc

That will allow you to connect and move to your local dir in two commands:

    you@host:~$ managwp
    ...
    www-data@mgmt-x-xxx:~$ gowp

You can now jump to the [usage](#usage) section.