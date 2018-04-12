#!/bin/bash

python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/education --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 
wp pll lang create Français fr fr_FR --path=/srv/hmuriel/jahia2wp-httpd/htdocs/epfl/education
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs/dcsl --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs/nal --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/publications --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 

python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/inside --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/inside/students --admin-password=jahia2wp --extra-config=vent-tools/generate.yml 

# Move the accred and tequila plugins to let for local connections
find /srv/hmuriel/jahia2wp-httpd/htdocs/ \( -iname "accred" -o -iname "tequila" \) -exec mv {} {}.bak \;