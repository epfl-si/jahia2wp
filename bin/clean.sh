#!/bin/bash

htDocsDir=`find volumes/srv/$1/ -name htdocs`


# If 'htdocs' folder found,
if [ "${htDocsDir}" != "" ]
then 

	echo -n "Following folder will be deleted, please confirm (${htDocsDir}) - (yes/no): " 
	read answer


	if [ "${answer}" = "yes" ]
	then 
		echo "Cleaning WP files..."
		if [ -e ${htDocsDir} ]
		then 
			sudo rm -r ${htDocsDir}
		fi  
	else
		echo "Skipping WP files deletion!"
	fi

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

