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


loc=`pwd`

cd ${SRC_DIR}
python jahia2wp.py export $1 https://jahia2wp-httpd/${1} idevelop --to-wordpress --installs-locked=yes ${2}
cd ${loc}