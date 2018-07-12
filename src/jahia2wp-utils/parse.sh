#!/bin/bash


source "/srv/${WP_ENV}/jahia2wp/src/jahia2wp-utils/config.sh"


if [ "$1" == "" ]
then
    echo "Site name missing"
    exit 1
fi

loc=`pwd`

cd ${SRC_DIR}
python jahia2wp.py parse $1
cd ${locl}


echo "done"

