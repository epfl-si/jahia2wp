#!/bin/bash

python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl --admin-password=jahia2wp --extra-config=generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/education --admin-password=jahia2wp --extra-config=generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research --admin-password=jahia2wp --extra-config=generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs --admin-password=jahia2wp --extra-config=generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs/dcsl --admin-password=jahia2wp --extra-config=generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/labs/nal --admin-password=jahia2wp --extra-config=generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/publications --admin-password=jahia2wp --extra-config=generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/epfl/research/publications/dcsl --admin-password=jahia2wp --extra-config=generate.yml 

python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/inside --admin-password=jahia2wp --extra-config=generate.yml 
python jahia2wp.py generate $WP_ENV http://jahia2wp-httpd/inside/students --admin-password=jahia2wp --extra-config=generate.yml 
