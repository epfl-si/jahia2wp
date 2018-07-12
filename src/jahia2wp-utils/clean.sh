#!/bin/bash


source "/srv/${WP_ENV}/jahia2wp/src/jahia2wp-utils/config.sh"


if [ "$1" == "" ]
then
    echo "Site name missing"
    exit 1
fi



python ${SRC_DIR}jahia2wp.py clean ${WP_ENV} https://jahia2wp-httpd/$1

echo -n "Removing extracted files... "
rm -rf "${EXPORT_DIR}/$1"
echo "done"

