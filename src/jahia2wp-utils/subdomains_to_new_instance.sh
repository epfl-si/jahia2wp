#!/bin/bash

echo " Quelle est la destination sur www? (ex: campus/associations/list/qed) "
read -r dest
echo " Quel est le nom du site sur subdomains? (ex : qed) " 
read -r site

TODAY=$((`date +%Y%m%d`))

if [ ${#destination} != 0 ] || [ ${#site} != 0 ]
then
	#Copier le site de subdomains vers www
	cp -r /srv/subdomains/${site}.epfl.ch/htdocs /srv/www/www.epfl.ch/htdocs/${dest}
	
	#Exporter la base de donnees du site en subdomains
	wp db export /backups/_srv_subdomains_${site}.epfl.ch_htdocs/${TODAY}.sql --path=/srv/subdomains/${site}.epfl.ch/htdocs/

	#Modifier le DB_NAME, DB_USER et DB_PASSWORD dans wp-config.php
	cat /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php | sed "s/'DB_NAME', '\(.*\).'/'DB_NAME', '\1_'/" > /srv/www/www.epfl.ch/htdocs/${dest}/wp-config2.php
	mv /srv/www/www.epfl.ch/htdocs/${dest}/wp-config2.php /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php 
	
	cat /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php | sed "s/'DB_USER', '\(.*\).'/'DB_USER', '\1_'/" > /srv/www/www.epfl.ch/htdocs/${dest}/wp-config2.php
        mv /srv/www/www.epfl.ch/htdocs/${dest}/wp-config2.php /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php

	cat /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php | sed "s/'DB_PASSWORD', '\(.*\).'/'DB_PASSWORD', '\1_'/" > /srv/www/www.epfl.ch/htdocs/${dest}/wp-config2.php
        mv /srv/www/www.epfl.ch/htdocs/${dest}/wp-config2.php /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php	
	
	#Modifier le WP_CONTENT_DIR dans wp-config.php
	sed -i "s|subdomains\/${site}.epfl.ch\/htdocs\/|www\/www.epfl.ch\/htdocs\/${dest}|g" /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php

	#Modification du fichier .htaccess 
	sed -i "s|RewriteBase /|RewriteBase /${dest}|g" /srv/www/www.epfl.ch/htdocs/${dest}/.htaccess
	sed -i "s|RewriteRule . /index.php|RewriteRule . /${dest}/index.php|g" /srv/www/www.epfl.ch/htdocs/${dest}/.htaccess

	#Creation de la base vide
	DB_USER=`grep DB_USER /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php |awk '{print $3}' |sed "s/'//g"` && DB_PASSWORD=`grep DB_PASSWORD /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php |awk '{print $3}' |sed "s/'//g"` && DB_NAME=`grep DB_NAME /srv/www/www.epfl.ch/htdocs/${dest}/wp-config.php |awk '{print $3}' |sed "s/'//g"` && mysql -h db-wwp.epfl.ch -u oswproot --password=Pei8vao6Teiv -e "CREATE USER '$DB_USER' IDENTIFIED BY '$DB_PASSWORD';CREATE DATABASE $DB_NAME;GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER';"

	#Faire l'import de la base de donnees du site en www
        wp db import /backups/_srv_subdomains_${site}.epfl.ch_htdocs/${TODAY}.sql --path=/srv/www/www.epfl.ch/htdocs/${dest}
	
	#Supprimer l'export de la base donnees du site
	rm /backups/_srv_subdomains_${site}.epfl.ch_htdocs/${TODAY}.sql

	#Search and replace
	wp --path=/srv/www/www.epfl.ch/htdocs/${dest} search-replace ${site}.epfl.ch www.epfl.ch/${dest} --skip-columns=guid

	#Activer theme 2018
	wp theme activate wp-theme-2018 --path=/srv/www/www.epfl.ch/htdocs/${dest}
	
	#Rafraichir les externals menus
	(cd /srv/www/www.epfl.ch/htdocs/${dest} 
	wp epfl-menus refresh)

	#Desintallations et desactivations des plugins 2010
	wp plugin uninstall --deactivate enlighter --path=/srv/www/www.epfl.ch/htdocs/${dest}
	wp plugin uninstall --deactivate shortcodes-ultimate --path=/srv/www/www.epfl.ch/htdocs/${dest}
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} EPFL-FAQ
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-faq
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-google-forms
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-grid
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-infoscience
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-infoscience-search
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-map
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-memento
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-news
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-people
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-scheduler
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} EPFL-Snippet
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-snippet
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-tableau
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-toggle
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-twitter
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-video
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-xml
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} EPFL-Share
	wp plugin deactivate --path=/srv/www/www.epfl.ch/htdocs/${dest} epfl-buttons
	
	##Desactiver et activer le plugin mainwp-child pour ajotuer le site dans wp-manager
	wp plugin deactivate mainwp-child --path=/srv/www/www.epfl.ch/htdocs/${dest}	
	wp plugin activate mainwp-child --path=/srv/www/www.epfl.ch/htdocs/${dest}
	
	#Activer plugins 2018
	wp plugin activate epfl --path=/srv/www/www.epfl.ch/htdocs/${dest}
	wp plugin activate wp-media-folder --path=/srv/www/www.epfl.ch/htdocs/${dest}
	
	#Activer automatiquement le coming soon
	cp /srv/www/jahia2wp/src/jahia2wp-utils/activation_coming-soon_copie_new_instance_prod_18.json /tmp/activation_coming-soon_copie_new_instance_prod_18_${site}
	sed -i "s|destname|${dest}|g" /tmp/activation_coming-soon_copie_new_instance_prod_18_${site}
	wp option update seed_csp4_settings_content --format=json --path=/srv/www/www.epfl.ch/htdocs/${dest} < /tmp/activation_coming-soon_copie_new_instance_prod_18_${site}
	rm /tmp/activation_coming-soon_copie_new_instance_prod_18_${site}

	#Mettre les configurations du plugin wp-media-folder
	python /srv/www/jahia2wp/src/jahia2wp.py update-plugins www https://www.epfl.ch/${dest} --plugin=wp-media-folder --extra-config=/srv/www/jahia2wp/functional_tests/extra.yaml --force-options

	#Fix plugins 2010 -> 2018
	python /srv/www/jahia2wp/src/jahia2wp.py shortcode-fix www https://www.epfl.ch/${dest}
	
	#Optimiser les images
	wp ewwwio optimize all --noprompt --path=/srv/www/www.epfl.ch/htdocs/${dest}
	wp media regenerate --only-missing --yes --path=/srv/www/www.epfl.ch/htdocs/${dest}

	#Mettre le resultat de la requete SQL dans un fichier .txt
        wp db query 'SELECT ID, post_date, post_content, post_title, post_name, post_status FROM wp_posts WHERE post_content like "%[epfl_infoscience %" and post_type="page" and post_status = "publish"' --path=/srv/www/www.epfl.ch/htdocs/${dest} > /tmp/contenu_des_pages_${site}.txt

	#Savoir quelles sont les pages qui contiennent l'ancien plugin infoscience
        myfile="/tmp/contenu_des_pages_${site}.txt"
        while IFS=$'\t' read -r -a myArray
        do
        	html=${myArray[2]}
		pos=`echo $html | grep -bo "epfl_infoscience"| sed 's/:.*$//'`		
		
		if [ -z "$pos" ]
                then
                        echo ""
                else
			poss=( $pos )
               		nbr=${#poss[@]}

                	for (( i=0; i<$nbr; i++ ))
                	do
				echo ${myArray[0]} ${myArray[4]} ${html:${poss[i]}:80}
                	done
                fi
        done < "$myfile"
	
	#Supprimer le fichier .txt
	rm -r /tmp/contenu_des_pages_${site}.txt
fi



