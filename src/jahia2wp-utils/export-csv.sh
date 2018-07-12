#!/bin/bash

source "/srv/${WP_ENV}/jahia2wp/src/jahia2wp-utils/config.sh"

# Edit value if needed
SITE_ROOT="https://migration-wp.epfl.ch/"

if [ "$1" == "" ]
then
    echo "Site name missing"
    exit 1
fi

if [ ! -e ${CSV_FILE} ]
then
    echo "CSV file (${CSV_FILE}) not found!"
    exit
fi

siteName=$1

tmpCSVFile="/tmp/${siteName}"

CSVSiteLine=`egrep "^${SITE_ROOT}${siteName}," ${CSV_FILE}`

if [ "${CSVSiteLine}" == "" ]
then
    echo "Site '${siteName}' not found in CSV file (${CSV_FILE} !"
    exit
fi

# Extracting infos and temporary CSV file generation
head -1 ${CSV_FILE} > ${tmpCSVFile}
echo "${CSVSiteLine}" >> ${tmpCSVFile}


loc=`pwd`

cd ${SRC_DIR}
echo "python ${SRC_DIR}jahia2wp.py export-many ${tmpCSVFile} $2"
cd ${loc}

rm ${tmpCSVFile}