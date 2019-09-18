#!/bin/sh

echo "Quel est le nom du site?"
read -r site

if [ ${#site} != 0 ]

then
	#backup du site en production subdomains

	BACKUP_PATH=/backups/temp

	echo "backup en cours..."
	python ../jahia2wp.py backup subdomains https://$site.epfl.ch
	echo "fin backup : " $site

	scp -r -P 32222 -o StrictHostKeyChecking=no /backups/temp/_srv_subdomains_$site.epfl.ch_htdocs www-data@test-ssh-wwp.epfl.ch:/tmp/lab-import/_srv_subdomains_$site.epfl.ch_htdocs

	rm -rf /backups/temp/_srv_subdomains_$site.epfl.ch_htdocs

fi
