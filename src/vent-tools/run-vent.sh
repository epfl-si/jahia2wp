#!/bin/bash

export ROOT_SITE=www.epfl.ch
CSV_FILE=../data/csv/ventilation-local.csv

ROOT_WP_DEST=/srv/$WP_ENV/$ROOT_SITE/
DEMO_SITE=/srv/$WP_ENV/dcsl.epfl.ch

# Switch to the src/ path.
cd /srv/$WP_ENV/jahia2wp/src/;

# ======= DEMO CODE ======
# Check if the destination root tree (arborescence) exists.
if [ ! -d $ROOT_WP_DEST ]; then
	echo "Destination root tree does not exist: $DEMO_SITE, calling generate.sh...";
	./vent-tools/generate.sh;
fi

# Check if the dcsl.epfl.ch folder exists
if [ ! -d $DEMO_SITE ]; then
	echo "Demo site dir does not exsit: $DEMO_SITE, calling exportmany.sh...";
	echo "################################"
	echo "IMPORTANT: If you are running on a local env, add an entry to the /etc/hosts of the mgmt container like:";
	echo "172.19.0.5	dcsl.epfl.ch"
	echo ", otherwise the REST api will fail without access to port 8080"
	echo "If you want to see the intermediate WP site https://dcsl.epfl.ch, also add an entry to your local /etc/hosts :"
	echo "127.0.0.1	dcsl.epfl.ch"
	echo "################################"
	./vent-tools/exportmany.sh;
	# Disable accred and tequila
	echo "Disabling accred and tequila plugins from $DEMO_SITE ...";
	find $DEMO_SITE -type d \( -iname "accred" -o -iname "tequila" \) -exec mv {} {}.bak \;
fi

# Delete all content (pages, media, menu, sidebars) from target WP destination tree.
./vent-tools/del-posts.sh

# Run the migration with the proper CSV and target destination tree. Encoding is important since CampToCamp did 
# not set a proper input/output encoding despite the environment being utf8!
PYTHONIOENCODING="utf-8" python jahia2wp.py migrate-urls $CSV_FILE $WP_ENV --root_wp_dest=$ROOT_WP_DEST