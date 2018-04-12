#!/bin/bash

mpath="/srv/hmuriel/jahia2wp-httpd/htdocs"
declare -a paths=("epfl" "epfl/education" "epfl/research" 
	"epfl/research/labs" "epfl/research/labs/dcsl" "epfl/research/labs/nal" 
	"epfl/research/publications" "inside" "inside/students")
for path in "${paths[@]}"
do
	ids=$(wp post list --post_type='page' --format=ids --path="${mpath}/${path}")
	if [ -n "$ids" ]; then
		echo "ids for $path: ${ids[@]}";
		wp post delete --force --path=$mpath/$path $ids
	else
		echo "No posts for $path"
	fi
done