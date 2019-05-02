#!/bin/bash

OUTFILE="/tmp/_srv_unm"

if [ -e ${OUTFILE} ]
then
    rm ${OUTFILE}
fi


echo -n "Extracting site list... "

for folder in `ls /srv/ | grep unm-`
do
    fullPath="/srv/${folder}"

    find ${fullPath} -maxdepth 3 -name "wp-config.php" -printf '%h\n' | sort >> ${OUTFILE}
done

echo "done (saved in ${OUTFILE}"
