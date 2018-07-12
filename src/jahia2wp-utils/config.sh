#!/bin/bash

# Edit file if needed

EXPORT_DIR="/srv/${WP_ENV}/jahia2wp/data/jahia-data"
SRC_DIR="/srv/${WP_ENV}/jahia2wp/src/"
CSV_FILE="/srv/${WP_ENV}/jahia2wp/data/csv/migrations.csv"

#######################################
# Don't edit after this line

# Recovering from config
ZIP_DIR="`echo ${JAHIA_ZIP_PATH}`"
if [ "${ZIP_DIR}" == "." ]
then
    ZIP_DIR=${SRC_DIR}
fi
