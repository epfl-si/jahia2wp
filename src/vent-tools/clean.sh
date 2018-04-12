#!/bin/bash

python jahia2wp.py clean $WP_ENV http://jahia2wp-httpd/epfl  
python jahia2wp.py clean $WP_ENV http://jahia2wp-httpd/epfl/education
python jahia2wp.py clean $WP_ENV http://jahia2wp-httpd/epfl/research  
python jahia2wp.py clean $WP_ENV http://jahia2wp-httpd/epfl/research/labs  
python jahia2wp.py clean $WP_ENV http://jahia2wp-httpd/epfl/research/labs/dcsl  
python jahia2wp.py clean $WP_ENV http://jahia2wp-httpd/epfl/research/labs/nal  
python jahia2wp.py clean $WP_ENV http://jahia2wp-httpd/epfl/research/publications  

python jahia2wp.py clean $WP_ENV http://jahia2wp-httpd/inside  
python jahia2wp.py clean $WP_ENV http://jahia2wp-httpd/inside/students 