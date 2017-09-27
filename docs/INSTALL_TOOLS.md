Tools Installation
==================

<!-- TOC -->

- [Docker & rights](#docker--rights)
- [Docker images](#docker-images)
- [Make](#make)
- [Python virtualenv](#python-virtualenv)

<!-- /TOC -->

## Docker & rights

Make sure that you have docker and docker-compose installed on your workstation:

```
$ docker --version
Docker version 17.06.2-ce, build cec0b72
$ docker-compose --version
docker-compose version 1.16.1, build 6d1ac219
```

If you don't have those version installed, follow the procedure here : https://docs.docker.com/engine/installation/linux/docker-ce/ubuntu/



If you don't have **docker-compose** installed, do the following

**Note :** Do not install it using ```sudo apt-get install docker-compose``` because you won't get the last version.


```
$ sudo apt-get install python-pip
$ sudo pip install docker-compose
```


If you had to install a fresh version of docker, beware of **user groups** : the user that will be used to execute the "make" commands below needs to be in the "docker" group. Otherwise you'll get an error with "make build".

```
$ sudo usermod -aG docker `whoami`
$ sudo usermod -aG www-data `whoami`
```

Now you have to log off and log in again to "enable" your new group. If you don't do this, you'll have errors later.

After log in again, you have to start docker services and check if everything is working fine

```
$ sudo service docker start
$ docker info
Containers: 0
 Running: 0
 Paused: 0
 Stopped: 0
...
```



## Docker images

You will need to clone the repository containing the images for the management container and apache.

    $ git clone git@github.com:epfl-idevelop/epfl-os-wp.git

You can now build the two images, which will be automatically found when needed.

    $ cd epfl-os-wp/build/httpd
    $ docker build -t camptocamp/httpd .
    ...
    $ cd ../mgmt
    $ docker build -t camptocamp/mgmt .
    ...

## Make

Make will also simplify your tasks

```
$ make --version
GNU Make 3.81
```


## Python virtualenv

Install virtualenv and create your first virtual environment

```
$ sudo pip install virtualenv
$ which python3
/usr/bin/python3

$ cd
$ mkdir virtualenvs
$ cd virtualenvs
$ virtualenv -p /usr/bin/python3 jahia2wp

$ echo "
alias vjahia2wp='source ~/virtualenvs/jahia2wp/bin/activate && cd ~/git-repos/jahia2wp && export PYTHONPATH=$PWD/src'
" >> ~/.bash_aliases

$ source ~/.bash_aliases
$ python --version
2.x
$ vjahia2wp
(jahia2wp) $ python --version
3.x

NB: pour sortir du virtualenv :
(jahiap) $ deactivate

```

Upgrade pip (you have to upgrade it after installing requirements otherwise it will fail because of permissions denied on some folders)

```
$ pip install --upgrade pip
```
