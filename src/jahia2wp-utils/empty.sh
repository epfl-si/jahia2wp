#!/bin/bash

# Import configuration
CONFIG_FILE="/srv/${WP_ENV}/jahia2wp/src/jahia2wp-utils/config.sh"

if [ ! -e ${CONFIG_FILE} ]
then
    echo "Config file (${CONFIG_FILE}) not found!"
    echo "Please create it from sample file."
    exit 1
fi
source ${CONFIG_FILE}

# Check parameters


if [ "$1" == "" ]
then
    echo "Site name missing"
    exit 1
fi


python jahia2wp.py generate lchaboudez https://jahia2wp-httpd/${1} --extra-config=/srv/${WP_ENV}/jahia2wp/functional_tests/extra.yaml --nosymlink $2
