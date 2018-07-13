#!/bin/bash

# Import configuration
CONFIG_FILE="/srv/${WP_ENV}/jahia2wp/src/jahia2wp-utils/config.sh"

if [ ! -e ${CONFIG_FILE} ]
then
    echo "Config file (${CONFIG_FILE}) not found!"
    exit 1
fi
source ${CONFIG_FILE}


# Check parameters

if [ "$1" == "" ]
then
    echo "Site name missing"
    exit 1
fi

python ${SRC_DIR}jahia2wp.py download $1 --username=root --host=jahia-prod.epfl.ch --zip-path=${ZIP_DIR}