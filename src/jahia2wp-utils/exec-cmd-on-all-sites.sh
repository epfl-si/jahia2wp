#!/bin/bash

# Check parameters

if [ "$#" -ne 2 ]
then
    echo "Usage: $0 <sitesRootPath> <CMDToExec>"

    exit 1
fi


SITES_ROOT_PATH=$1
CMD_TO_EXEC=$2
TMP_FILE="/tmp/inventory"

CURDIR=`pwd`


echo -n "Extracting site list... "
find ${SITES_ROOT_PATH} -name "wp-config.php" -printf '%h\n' > ${TMP_FILE}
echo "done"

echo "Executing command on each site..."

while read s
do
  echo "${s}..."
  cd ${s}
  eval "${CMD_TO_EXEC}"
done < ${TMP_FILE}

cd ${CURDIR}