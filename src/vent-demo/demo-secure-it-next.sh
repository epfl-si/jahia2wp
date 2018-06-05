#!/bin/bash

export ROOT_SITE=vpsi-next.epfl.ch
CSV_FILE=/srv/$WP_ENV/jahia2wp/src/vent-demo/data/ventilation-secure-it-next.csv

ROOT_WP_DEST=/srv/$WP_ENV/$ROOT_SITE/htdocs

# This DEMO reads the csv and migrates the content under the ROOT_SITE
# ***** It does not export missing sites nor creates destination sites. ********

# Generate target site (as stated in the rules ventilation-secure-it-next.csv)
# python jahia2wp.py generate $WP_ENV https://secure-it-next.epfl.ch/ --admin-password=admin --extra-config=vent-demo/data/generate-enfr.yml
# python jahia2wp.py generate $WP_ENV https://vpsi-next.epfl.ch/security --admin-password=admin --extra-config=vent-demo/data/generate-enfr.yml
# Move the accred and tequila plugins to let for local connections
# find /srv/$WP_ENV/$ROOT_SITE/ -type d \( -iname "accred" -o -iname "tequila" \) -print0 | xargs -0 -I {} mv {} {}.bak

# For this particular case, it's necessary to manually export / import the content from secure-it-next.epfl.ch since it's not a JAHIA export.

# 1) Delete all content (pages, media, menu, sidebars) from target WP destination tree.
./vent-demo/utils/del-posts.sh "$ROOT_WP_DEST"

# 2) RUN the migration. Force utf8 for io since c2c container uses a variant of ascii for io.
PYTHONIOENCODING="utf-8" python jahia2wp.py migrate-urls $CSV_FILE $WP_ENV --root_wp_dest=$ROOT_WP_DEST
