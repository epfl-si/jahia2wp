#!/bin/bash

# Check parameters

if [ "$#" -lt 1 ]
then
	echo "Usage: $0 <sitesRootPath> [<maxSearchDepth>]"

        exit 1
fi

SITES_ROOT_PATH=`echo ${1%/}`

OUTFILE=`echo "${SITES_ROOT_PATH}" | sed 's/\//_/g'`
OUTFILE="/tmp/${OUTFILE}"

echo -n "Extracting site list... "

maxDepth=""
if [ "$2" != "" ]
then
	maxDepth=" -maxdepth $2"
	echo -n "(max depth: $2)... "
fi

find ${SITES_ROOT_PATH} ${maxDepth} -name "wp-config.php" -printf '%h\n' | sort > ${OUTFILE}
echo "done (saved in ${OUTFILE}"
