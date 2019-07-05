#!/bin/bash

# Recherche du chemin jusqu'au dossier courant
CURRENT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

# Inclusion des fonctions génériques
source ${CURRENT_DIR}/functions.sh

read -p "Quel est le nom du site? " site 
read -p "Quel est l'url du site? " url 

#Deplacer les fichiers du site
execCmd "mv /srv/subdomains/${site}.epfl.ch/htdocs /srv/sandbox/archive-wp.epfl.ch/htdocs/${site}"

#Modifier le fichier wp-config.php
execCmd "sed -i \"s/subdomains\/${site}.epfl.ch\/htdocs\//sandbox\/archive-wp.epfl.ch\/htdocs\/${site}/\" /srv/sandbox/archive-wp.epfl.ch/htdocs/${site}/wp-config.php"

#Editer le fichier .htacess dans subdomains
mkdir /srv/subdomains/${site}.epfl.ch/htdocs
execCmd "echo \"# BEGIN WordPress-Redirects-After-Ventilation\" > /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess"
execCmd "echo \"RewriteRule ^(.*)$ ${url} [L,QSA,R=301]\" >> /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess"
execCmd "echo \"# END WordPress-Redirects-After-Ventilation\" >> /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess"

#Editer le fichier .htacess dans sandbox
execCmd "sed -i \"s|RewriteBase /|RewriteBase /${site}/|g\" /srv/sandbox/archive-wp.epfl.ch/htdocs/${site}/.htaccess"
execCmd "sed -i \"s|RewriteRule . /index.php|RewriteRule . /${site}/index.php|g\" /srv/sandbox/archive-wp.epfl.ch/htdocs/${site}/.htaccess"

#Faire search and replace du site en prod vers archive
execCmd "wp search-replace ${site}.epfl.ch archive-wp.epfl.ch/${site} --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site}"

#Activer le coming soon et changer le texte du coming soon
execCmd "cp activation_coming-soon_archive.json /tmp/activation_coming-soon_archive_${site}"
execCmd "sed -i \"s|sitename|${site}|g\" /tmp/activation_coming-soon_archive_${site}"
execCmd "wp option update seed_csp4_settings_content --format=json --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site} < /tmp/activation_coming-soon_archive_${site}"
rm /tmp/activation_coming-soon_archive_${site}

#Mettre les configurations du plugin coming-soon
execCmd "python ../jahia2wp.py update-plugins subdomains https://archive-wp.epfl.ch/${site} --plugin=coming-soon --extra-config=/srv/www/jahia2wp/functional_tests/extra.yaml --force-options"

#Supprimer le plugin mainwp child
wp plugin deactivate mainwp-child --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site}
wp plugin delete mainwp-child --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site}
