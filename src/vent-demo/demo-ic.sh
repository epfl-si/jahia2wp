#!/bin/bash

export ROOT_SITE=www.epfl.ch
CSV_FILE=/srv/$WP_ENV/jahia2wp/src/vent-demo/data/ventilation-ic.csv

ROOT_WP_DEST=/srv/$WP_ENV/$ROOT_SITE

# This DEMO reads the csv and migrates content under the ROOT_SITE
# ***** It does not export missing sites nor creates destination sites. ********

# 1) Delete all content (pages, media, menu, sidebars) from target WP destination tree.
./vent-demo/utils/del-posts.sh

# 2) RUN the migration. Force utf8 for io since c2c container uses a variant of ascii for io.
PYTHONIOENCODING="utf-8" python jahia2wp.py migrate-urls $CSV_FILE $WP_ENV --root_wp_dest=$ROOT_WP_DEST --strict