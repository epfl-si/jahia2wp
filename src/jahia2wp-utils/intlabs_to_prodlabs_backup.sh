#!/bin/bash

echo "Quel est le nom du site?"
read -r site

if [ ${#site} != 0 ]

then 
	#Obtenir les donnees pour creer un site wopress vide
	blogdescription=`wp option get blogdescription --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/${site}`
	blogname=`wp option get blogname --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/${site}`
	unit=`wp option get plugin:epfl_accred:unit --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/${site}`
	unit_id=`wp option get plugin:epfl_accred:unit_id --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/${site}`
	langue=`wp polylang  --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/${site} languages`

	mapfile -t ligne <<< "$langue"

	nbr_ligne=${#ligne[@]}

	for (( i=0; i<${nbr_ligne}; i++ )); 

	do
		if [[ ${ligne[$i]} == *"[DEFAULT]"* ]]
						
		then
			langues=${ligne[$i]:0:2} 
		fi
	done

	for (( i=0; i<${nbr_ligne}; i++ )); 

	do

		if [[ ${ligne[$i]} != *"[DEFAULT]"* ]]

		then
			langues=$langues","${ligne[$i]:0:2} 
		fi
	done

	#Mettre l'entete dans le csv
	echo "wp_site_url,wp_tagline,wp_site_title,site_type,openshift_env,category,theme,theme_faculty,status,installs_locked,updates_automatic,langs,unit_name,unit_id," > ${site}.csv

	#Mettre les donnees dans le csv
	echo "https://www.epfl.ch/labs/"$site","\"$blogdescription\"","$blogname",wordpress,labs,GeneralPublic,epfl,,yes,yes,yes,"\"$langues\"","$unit","$unit_id"," >> ${site}.csv

	#Copier le fichier labs_to_prod dans labs
	scp -P 32222 -o StrictHostKeyChecking=no /srv/int/jahia2wp/src/jahia2wp-utils/${site}.csv www-data@ssh-wwp.epfl.ch:/tmp/${site}.csv
				
	#Exporter la base de données du site
	wp db export /tmp/${site}.sql --path=/srv/int/migration-wp.epfl.ch/htdocs/labs/${site}

	#Compresser les medias et .htaccess
	tar -czf /tmp/${site}.tar.gz -C /srv/int/migration-wp.epfl.ch/htdocs/labs/${site}/wp-content/ uploads
	
	#Copier la db et le tar dans LABS
	scp -P 32222 -o StrictHostKeyChecking=no /tmp/${site}.sql www-data@ssh-wwp.epfl.ch:/tmp/
	scp -P 32222 -o StrictHostKeyChecking=no /tmp/${site}.tar.gz www-data@ssh-wwp.epfl.ch:/tmp/
		
	#Nettoyage des fichiers temporaires
	rm -rf /tmp/${site}.sql
	rm -rf /srv/int/jahia2wp/src/jahia2wp-utils/${site}.csv
	rm -rf /tmp/${site}.tar.gz


else
	./intlabs_to_prodlabs.sh
fi



