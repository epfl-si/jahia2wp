#!/bin/bash

# Check parameters

if [ "$#" -ne 1 ]
then
    echo "Usage: $0 <sitesRootPath>"

    exit 1
fi


SITES_ROOT_PATH=$1
TMP_FILE_INVENTORY="/tmp/inventory.htaccess"
TMP_FILE_PAGE_LIST="/tmp/pageList"
TMP_FILE_PAGE_REDIRECT="/tmp/redirectList"

echo -n "Extracting site list... "
find ${SITES_ROOT_PATH} -maxdepth 3 -name "wp-config.php" -printf '%h\n' > ${TMP_FILE_INVENTORY}
echo "done"

echo "Looping through sites"

while read pathToSite
do
    echo -n "${pathToSite}... "

    currentHtaccess="${pathToSite}/.htaccess"

    # Listing site pages
    wp post list --post_type=page --field=post_name --format=csv --path=${pathToSite} > ${TMP_FILE_PAGE_LIST}
    # Extract redirect targets
    cat ${currentHtaccess} | grep "Redirect 301" | awk '{print $NF}' | sort | uniq | awk -F"/" '{print $(NF-1)}' > ${TMP_FILE_PAGE_REDIRECT}

    nbFix=0

    while read target
    do
        if [ "`egrep \"^${target}$\" ${TMP_FILE_PAGE_LIST}`" == "" ]
        then
            # Removing error from htaccess
            sed -i "/\/${target}\/$/d" ${currentHtaccess}

            let nbFix=nbFix+1
        fi

    done < ${TMP_FILE_PAGE_REDIRECT}

    rm ${TMP_FILE_PAGE_REDIRECT}

    if [ ${nbFix} -eq 0 ]
    then
        echo "Correct"
    else
        echo "${nbFix} fix done for site"
    fi


    rm ${TMP_FILE_PAGE_LIST}

done < ${TMP_FILE_INVENTORY}

rm ${TMP_FILE_INVENTORY}

