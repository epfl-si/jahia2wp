#!/bin/bash


source "/srv/${WP_ENV}/jahia2wp/src/jahia2wp-utils/config.sh"


if [ "$1" == "" ]
then
    echo "Site name missing"
    exit 1
fi

python ${SRC_DIR}jahia2wp.py parse $1

echo "done"

