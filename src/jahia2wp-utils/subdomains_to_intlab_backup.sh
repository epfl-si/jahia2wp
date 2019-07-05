#!/bin/bash

# Recherche du chemin jusqu'au dossier courant
CURRENT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

 # Inclusion des fonctions génériques
source ${CURRENT_DIR}/functions.sh

echo "Quel est le nom du site?"
read -r site

if [ ${#site} != 0 ]

then
	#backup du site en production subdomains

	BACKUP_PATH=/backups/temp

	echo "backup en cours..."
	execCmd "python ../jahia2wp.py backup subdomains https://$site.epfl.ch"
	echo "fin backup : " $site

	execCmd  "scp -r -P 32222 -o StrictHostKeyChecking=no /backups/temp/_srv_subdomains_$site.epfl.ch_htdocs www-data@test-ssh-wwp.epfl.ch:/tmp/_srv_subdomains_$site.epfl.ch_htdocs"

	rm -rf /backups/temp/_srv_subdomains_$site.epfl.ch_htdocs

fi
