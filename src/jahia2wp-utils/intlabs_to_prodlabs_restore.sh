#!/bin/bash

# Recherche du chemin jusqu'au dossier courant
CURRENT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

# Inclusion des fonctions génériques
source ${CURRENT_DIR}/functions.sh



echo "Quel est le nom du site?"

read -r site


if [ ${#site} != 0 ]

then
	#Generer le site vide
	execCmd "python ../jahia2wp.py generate-many /tmp/${site}.csv"

	#Decompresser le tar des medias et .htaccess
	tar -xzvf /tmp/${site}.tar.gz -C /srv/labs/www.epfl.ch/htdocs/labs/${site}/wp-content/
	
	#Restaurer la base de donnees sur le site en prod
	execCmd "wp db import /tmp/${site}.sql --path=/srv/labs/www.epfl.ch/htdocs/labs/${site}"

	#Search and replace du site en int vers prod
	execCmd "wp search-replace migration-wp.epfl.ch/labs/${site} www.epfl.ch/labs/${site} --path=/srv/labs/www.epfl.ch/htdocs/labs/${site} --skip-columns=guid"
	#Supprimer les fichiers temporaires de backup sur la prod
	rm /tmp/${site}.sql
	rm /tmp/${site}.tar.gz
      	rm /tmp/${site}.csv

	#Desactiver et activer le plugin mainwp-child pour ajotuer le site dans wp-manager
	execCmd "wp plugin deactivate mainwp-child --path=/srv/labs/www.epfl.ch/htdocs/labs/${site}"
	execCmd "wp plugin activate mainwp-child --path=/srv/labs/www.epfl.ch/htdocs/labs/${site}"

	#Rafraichir les externals menus
	execCmd "wp epfl menus refresh --path=/srv/labs/www.epfl.ch/htdocs/labs/${site}"
	
	#Activer tous les plugins
	execCmd "wp plugin activate --all --path=/srv/labs/www.epfl.ch/htdocs/labs/${site}"
	
	#Desactiver le coming-soon
	execCmd "wp plugin deactivate coming-soon --path=/srv/labs/www.epfl.ch/htdocs/labs/${site}"

	#Optimisation les medias du site
    	execCmd "wp ewwwio optimize all --noprompt --path=/srv/labs/www.epfl.ch/htdocs/labs/${site}"
    	execCmd "wp media regenerate --only-missing --yes --path=/srv/labs/www.epfl.ch/htdocs/labs/${site}"
		
	#Mettre l'ancien site dans le sandbox
	#Deplacer les fichiers du site
	execCmd "mv /srv/subdomains/${site}.epfl.ch/htdocs /srv/sandbox/archive-wp.epfl.ch/htdocs/${site}"

	#Modifier le fichier wp-config.php
	sed -i "s/subdomains\/${site}.epfl.ch\/htdocs\//sandbox\/archive-wp.epfl.ch\/htdocs\/${site}/" /srv/sandbox/archive-wp.epfl.ch/htdocs/${site}/wp-config.php

	#Editer le fichier .htacess dans subdomains
	mkdir /srv/subdomains/${site}.epfl.ch/htdocs
	echo "# BEGIN WordPress-Redirects-After-Ventilation" > /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess
	echo "RewriteRule ^(.*)$ https://www.epfl.ch/labs/${site}/\$1 [L,QSA,R=301]" >> /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess
	echo "# END WordPress-Redirects-After-Ventilation" >> /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess

	#Editer le fichier .htacess dans sandbox
	sed -i "s|RewriteBase /|RewriteBase /$site/|g" /srv/sandbox/archive-wp.epfl.ch/htdocs/$site/.htaccess
	sed -i "s|RewriteRule . /index.php|RewriteRule . /$site/index.php|g" /srv/sandbox/archive-wp.epfl.ch/htdocs/$site/.htaccess

	#Faire search and replace du site en prod vers archive
	execCmd "wp search-replace ${site}.epfl.ch archive-wp.epfl.ch/${site} --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site}"

	#Activer le coming soon et changer le texte du coming soon
   	cp activation_coming-soon_archive.json /tmp/activation_coming-soon_archive_${site}
    	sed -i "s|sitename|${site}|g" /tmp/activation_coming-soon_archive_${site}
    	execCmd "wp option update seed_csp4_settings_content --format=json --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site} < /tmp/activation_coming-soon_archive_${site}"
    	rm /tmp/activation_coming-soon_archive_${site}
	
	#Supprimer le plugin mainwp child
	execCmd "wp plugin deactivate mainwp-child --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site}"
	execCmd "wp plugin delete mainwp-child --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site}"

else
	./intlabs_to_prodlabs.sh
fi

