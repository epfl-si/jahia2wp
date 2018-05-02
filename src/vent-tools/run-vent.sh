#!/bin/bash

export ROOT_SITE=www.epfl.ch
CSV_FILE=../data/csv/ventilation-local.csv

ROOT_WP_DEST=/srv/$WP_ENV/$ROOT_SITE/

# Switch to the src/ path.
cd /srv/$WP_ENV/jahia2wp/src/;
# Delete all content (pages, media, menu, sidebars) from target WP destination tree.
./wp-tools/del-posts.sh

# Run the migration with the proper CSV and target destination tree. Encoding is important since CampToCamp did 
# not set a proper input/output encoding despite the environment being utf8!
PYTHONIOENCODING="utf-8" python jahia2wp.py migrate-urls $CSV_FILE $WP_ENV --root_wp_dest=$ROOT_WP_DEST