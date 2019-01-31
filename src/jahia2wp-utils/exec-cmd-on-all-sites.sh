#!/bin/bash

# Check parameters

if [ "$#" -ne 2 ]
then
    echo "Usage: $0 <sitesRootPath> <CMDToExec>"

    exit 1
fi



CMD_TO_EXEC=$2
SITES_ROOT_PATH=`echo ${1%/}`

OUTFILE=`echo "${SITES_ROOT_PATH}" | sed 's/\//_/g'`
OUTFILE="/tmp/${OUTFILE}"

if [ ! -e ${OUTFILE} ]
then
	echo "Inventory file '${OUTFILE}' not found. Run inventory.sh before."
	exit 1
fi

CURDIR=`pwd`

echo "Executing command on each site..."

while read s
do
  echo "${s}..."
  cd ${s}
  eval "${CMD_TO_EXEC}"
done < ${OUTFILE}

cd ${CURDIR}