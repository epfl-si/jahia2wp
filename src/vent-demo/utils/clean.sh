#!/bin/bash
if [ -n "$ROOT_SITE" ]; then
	ROOT_SITE=www.epfl.ch
fi

python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/ 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/schools 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/schools/ic 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/schools/enac 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/research 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/research/domains 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/research/domains/laboratories 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/research/domains/laboratories/dcsl 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/research/domains/ic 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/research/domains/enac 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/innovation 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/vpsi 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/education 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/inside 
python jahia2wp.py clean $WP_ENV http://$ROOT_SITE/inside/students 

rm -r /srv/$WP_ENV/$ROOT_SITE/htdocs