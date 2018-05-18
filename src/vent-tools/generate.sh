#!/bin/bash

#
# Generate the sites tree from the source of truth
#
# For now it's hardcoded, but in the future it will take a CSV file as a parameter.

export ROOT_SITE=www.epfl.ch

python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/ --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/schools --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/schools/ic --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/schools/enac --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains/laboratories --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains/laboratories/dcsl --admin-password=admin --extra-config=vent-tools/generate.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains/ic --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains/ic/dcsl --admin-password=admin --extra-config=vent-tools/generate.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains/enac --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/innovation --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/vpsi --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/education --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/inside --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/inside/students --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 

#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/education --admin-password=admin --extra-config=vent-tools/generate-enfr.yml
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research --admin-password=admin --extra-config=vent-tools/generate-enfr.yml
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs --admin-password=admin --extra-config=vent-tools/generate-enfr.yml 
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs/dcsl --admin-password=admin --extra-config=vent-tools/generate.yml 
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs/nal --admin-password=admin --extra-config=vent-tools/generate.yml
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/publications --admin-password=admin --extra-config=vent-tools/generate.yml 

#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/inside --admin-password=admin --extra-config=vent-tools/generate-enfr.yml
#python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/inside/students --admin-password=admin --extra-config=vent-tools/generate-enfr.yml

# Move the accred and tequila plugins to let for local connections
find /srv/$WP_ENV/$ROOT_SITE/ -type d \( -iname "accred" -o -iname "tequila" \) -exec mv {} {}.bak \;
