# the .env file provides the default values for all environments variables used by
# - docker-compose, as described in https://docs.docker.com/compose/environment-variables/#the-env-file
# - make, by including .env in Makefile to install/clean worpdresses
# - python script, to connect to DB & make use of wp-cli


# DB credentials
export MYSQL_ROOT_PASSWORD=secret
export MYSQL_DB_HOST=db
export MYSQL_SUPER_USER=root
export MYSQL_SUPER_PASSWORD=secret


# WP variables
export WP_VERSION=latest
export WP_ADMIN_USER=admin
export WP_ADMIN_EMAIL=admin@example.com
export WP_PORT_HTTP=80
export WP_PORT_HTTPS=443


# WP MANAGEMENT
export WP_PORT_PHPMA=8080
export WP_PORT_SSHD=2222
export BACKUP_PATH=/tmp/backups


# JAHIA variables
export JAHIA_ZIP_PATH=.
export JAHIA_USER=admin
export JAHIA_PASSWORD=secret
export JAHIA_HOST=localhost
