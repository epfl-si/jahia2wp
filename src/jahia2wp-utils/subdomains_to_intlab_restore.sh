#!/bin/sh

echo "Quel est le nom du site ?"
read -r site

if [ ${#site} != 0 ]

then
	#Restauration des fichiers dans le repertoire du site
	cd /tmp/lab-import/_srv_subdomains_$site.epfl.ch_htdocs/

	tar xzvf /tmp/lab-import/_srv_subdomains_$site.epfl.ch_htdocs/*_full.tar.gz -C /tmp/lab-import/_srv_subdomains_$site.epfl.ch_htdocs

	#Deplacer les fichiers dans le bon repertoire
	mv /tmp/lab-import/_srv_subdomains_$site.epfl.ch_htdocs/srv/subdomains/$site.epfl.ch/htdocs /srv/int/migration-wp.epfl.ch/htdocs/labs/$site

	#Modifier la valeur dans le fichier wp-config.php du site sur INT par db-wwp.epfl.ch par test-db-wwp.epfl.ch
	cd /srv/int/migration-wp.epfl.ch/htdocs/labs/$site

	sed -i 's/db-wwp.epfl.ch/test-db-wwp.epfl.ch/g' /srv/int/migration-wp.epfl.ch/htdocs/labs/$site/wp-config.php

	#Creation de la base vide
	DB_USER=`grep DB_USER /srv/int/migration-wp.epfl.ch/htdocs/labs/$site/wp-config.php |awk '{print $3}' |sed "s/'//g"` && DB_PASSWORD=`grep DB_PASSWORD /srv/int/migration-wp.epfl.ch/htdocs/labs/$site/wp-config.php |awk '{print $3}' |sed "s/'//g"` && DB_NAME=`grep DB_NAME /srv/int/migration-wp.epfl.ch/htdocs/labs/$site/wp-config.php |awk '{print $3}' |sed "s/'//g"` && mysql -h test-db-wwp.epfl.ch -u oswproot --password=Pei8vao6Teiv -e "CREATE USER '$DB_USER' IDENTIFIED BY '$DB_PASSWORD';CREATE DATABASE $DB_NAME;GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER';"
	
	wp db import /tmp/lab-import/_srv_subdomains_$site.epfl.ch_htdocs/*.sql --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site

	#Suppression de l'importation des backups
	rm -rf /tmp/lab-import/_srv_subdomains_$site.epfl.ch_htdocs

	#Changement de .htaccess
	sed -i "s|RewriteBase /|RewriteBase /labs/$site|g" /srv/int/migration-wp.epfl.ch/htdocs/labs/$site/.htaccess

	sed -i "s|RewriteRule . /index.php|RewriteRule . /labs/$site/index.php|g" /srv/int/migration-wp.epfl.ch/htdocs/labs/$site/.htaccess

	#Mettre a jour les URL du site
	wp --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site/ search-replace $site.epfl.ch migration-wp.epfl.ch/labs/$site --skip-columns=guid

	#Mettre le nouveau theme 2018
	mkdir /tmp/theme_2018
	
	curl -o /tmp/theme_2018/theme_2018.py https://raw.githubusercontent.com/epfl-idevelop/wp-ops/master/docker/wp-base/install-plugins-and-themes.py
	
	(cd /tmp/theme_2018/
	chmod +x theme_2018.py)

	(cd /srv/int/migration-wp.epfl.ch/htdocs/labs/$site/wp-content/themes
	python /tmp/theme_2018/theme_2018.py theme_2018 https://github.com/epfl-idevelop/wp-theme-2018/tree/dev/wp-theme-2018)

	#Activer le theme 2018
	wp theme activate wp-theme-2018 --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site

	#Suppression de theme_2018.py
	rm -r /tmp/theme_2018
	
	#Desintallations des plugins 2010
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site enlighter
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site EPFL-FAQ
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-faq
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-google-forms
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-grid
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-infoscience
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-infoscience-search
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-map
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-memento
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-news
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-people
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-scheduler
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site EPFL-Snippet
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-tableau
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-toggle
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-twitter
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-video
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-xml
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site EPFL-Share
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site epfl-buttons
	wp plugin uninstall --deactivate --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/$site shortcodes-ultimate

	#Installer plugins 2018
	/srv/int/venv/bin/python /srv/int/jahia2wp/src/jahia2wp.py update-plugins int https://migration-wp.epfl.ch/labs/$site/ --plugin=epfl --extra-config=/srv/int/jahia2wp/functional_tests/extra.yaml --force-plugin

	/srv/int/venv/bin/python /srv/int/jahia2wp/src/jahia2wp.py update-plugins int https://migration-wp.epfl.ch/labs/$site/ --plugin=wp-media-folder --extra-config=/srv/int/jahia2wp/functional_tests/extra.yaml --force-plugin

	#Fix plugins 2010 -> 2018
	/srv/int/venv/bin/python /srv/int/jahia2wp/src/jahia2wp.py shortcode-fix int https://migration-wp.epfl.ch/labs/$site/ 

	#Mettre le resultat de la requete SQL dans un fichier .txt
        wp db query 'SELECT ID, post_date, post_content, post_title, post_name, post_status FROM wp_posts WHERE post_content like "%[epfl_infoscience %" and post_type="page" and post_status = "publish"' --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/${site} > /tmp/contenu_des_pages_${site}.txt
        
	#Savoir quelles sont les pages qui contiennent infoscience
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

