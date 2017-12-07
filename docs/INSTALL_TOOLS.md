Tools Installation
==================

<!-- TOC -->

- [Docker & rights](#docker--rights)
- [Make](#make)

<!-- /TOC -->

## Docker & rights

Make sure that you have docker and docker-compose installed on your workstation:

    $ docker --version
    Docker version 17.06.2-ce, build cec0b72
    $ docker-compose --version
    docker-compose version 1.16.1, build 6d1ac219

If you don't have those version installed, follow the procedure described on [the official documentation](https://docs.docker.com/engine/installation/linux/docker-ce/ubuntu/)

If you don't have **docker-compose** installed, do the following:

**Note :** Do not install it using ```sudo apt-get install docker-compose``` because you won't get the last version.

    $ sudo apt-get install python-pip
    ...
    $ sudo pip install docker-compose
    ...

If you had to install a fresh version of docker, beware of **user groups** : the user that will be used to execute the "make" commands below needs to be in the "docker" group. Otherwise you'll get an error with "make build".

    sudo usermod -aG docker `whoami`
    sudo usermod -aG www-data `whoami`

Now you have to log off and log in again to "enable" your new group. If you don't do this, you'll have errors later.

After log in again, you have to start docker services and check if everything is working fine:

    $ sudo service docker start
    $ docker info
    Containers: 0
    Running: 0
    Paused: 0
    Stopped: 0
    ...

## Make

Make will also simplify your tasks:

    $ make --version
    GNU Make 3.81
