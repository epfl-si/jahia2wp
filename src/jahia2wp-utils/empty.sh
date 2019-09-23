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
    echo "Create an UN-SYMLINKED site"
    echo "Usage: ./empty.sh <siteName> [<optionalArgs>]"
    exit 1
fi

# $2 = Optional parameter
python ${SRC_DIR}jahia2wp.py generate ${WP_ENV} ${SITE_ROOT}${1} --extra-config=${SRC_DIR}../functional_tests/extra.yaml --nosymlink $2
