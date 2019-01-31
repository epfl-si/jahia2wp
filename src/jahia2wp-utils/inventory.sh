#!/bin/bash

# Check parameters

if [ "$#" -ne 1 ]
then
	echo "Usage: $0 <sitesRootPath>"

        exit 1
fi
SITES_ROOT_PATH=`echo ${1%/}`

OUTFILE=`echo "${SITES_ROOT_PATH}" | sed 's/\//_/g'`
OUTFILE="/tmp/${OUTFILE}"

echo -n "Extracting site list... "
find ${SITES_ROOT_PATH} -name "wp-config.php" -printf '%h\n' | sort > ${OUTFILE}
echo "done (saved in ${OUTFILE}"