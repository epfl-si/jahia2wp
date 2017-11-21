#!/bin/bash

htDocsDir=`find volumes/srv/$1/ -name htdocs`

# If one or more 'htdocs' folder found,
if [ "${htDocsDir}" != "" ]
then

	# looping through 'htdocs' folder found using FD 9 (http://mywiki.wooledge.org/BashFAQ/089)
	while read -r path <&9
	do
		echo -n "Following folder will be deleted, please confirm (${path}) - (yes/no): "
		read -r answer


		if [ "${answer}" = "yes" ]
		then
			echo "Cleaning WP files ($path)..."
			if [ -e ${path} ]
			then
				sudo rm -r ${path}
			fi
		else
			echo "Skipping WP files deletion!"
		fi

	done 9<<< "${htDocsDir}"





else
	echo "WP files already cleaned!"

fi

if [ -e "volumes/db" ]
then
	echo "Cleaning DB files..."
	sudo rm -r "volumes/db"
else
	echo "DB files already cleaned!"
fi
