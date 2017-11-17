#!/bin/bash

chown -R www-data:www-data /var/www/.ssh
chmod 0700 /var/www/.ssh

exec "$@"
