#!/bin/bash

# Check parameters

if [ "$#" -ne 2 ]
then
    echo "Usage: $0 <sitesRootPath> <wpCliToExec>"

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

echo "Executing command on each site..."

while read s
do
  echo "${s}..."
  eval "${CMD_TO_EXEC} --path=$s"

done < ${OUTFILE}