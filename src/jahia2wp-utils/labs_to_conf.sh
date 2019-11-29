#!/bin/bash

read -p "Quel est le nom du site?"
read -r site

#Déplacer le contenu du site vers l'url 2010
mv /srv/labs/www.epfl.ch/htdocs/labs/${site}/{.[!.],}* /srv/subdomains/${site}.epfl.ch/htdocs/.

#Faire le search and replace du site
wp search-replace www.epfl.ch/labs/${site} ${site}.epfl.ch --path=/srv/subdomains/${site}.epfl.ch/htdocs/ --skip-columns=guid

#Editer le fichier .htacess
sed -i "s|RewriteBase /labs/${site}/|RewriteBase /|g" /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess
sed -i "s|RewriteRule . /labs/${site}/index.php|RewriteRule . /index.php|g" /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess

#Editer le fichier wp-config.php 
sed -i "s|define('WP_CONTENT_DIR', '/srv/labs/www.epfl.ch/htdocs/labs/${site}/wp-content');|define('WP_CONTENT_DIR', '/srv/subdomains/${site}.epfl.ch/htdocs/wp-content');|g" /srv/subdomains/${site}.epfl.ch/htdocs/wp-config.php
