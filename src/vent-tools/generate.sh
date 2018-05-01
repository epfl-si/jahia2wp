#!/bin/bash

#
# Generate the sites tree from the source of truth
#
# For now it's hardcoded, but in the future it will take a CSV file as a parameter.

python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl --admin-password=jahia2wp --extra-config=vent-tools/generate-enfr.yml 
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/education --admin-password=jahia2wp --extra-config=vent-tools/generate-enfr.yml
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research --admin-password=jahia2wp --extra-config=vent-tools/generate-enfr.yml
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs --admin-password=jahia2wp --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs/dcsl --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs/nal --admin-password=jahia2wp --extra-config=vent-tools/generate.yml
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/publications --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 

#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/inside --admin-password=jahia2wp --extra-config=vent-tools/generate-enfr.yml
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/inside/students --admin-password=jahia2wp --extra-config=vent-tools/generate-enfr.yml

# Move the accred and tequila plugins to let for local connections
find /srv/$WP_ENV/jahia2wp-httpd/htdocs/ \( -iname "accred" -o -iname "tequila" \) -exec mv {} {}.bak \;