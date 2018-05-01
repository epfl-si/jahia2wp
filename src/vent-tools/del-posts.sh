#!/bin/bash

mpath="/srv/$WP_ENV/jahia2wp-httpd/htdocs"
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
	# Delete all attachments
	media=$(wp post list --post_type='attachment' --format=ids --path="${mpath}/${path}")
	if [ -n "$media" ]; then
		echo "Deleting media / attachments: $media";
		wp post delete $media --path="${mpath}/${path}";
	fi
	# Delete any remaining menu entries
	menu_list=$(wp menu list --fields=term_id --format=ids --path="${mpath}/${path}")
	for menu in $menu_list
	do
		db_ids=$(wp menu item list $menu --fields=db_id --format=ids --path="${mpath}/${path}")
		if [ -n "$db_ids" ]; then
			echo "menu entries from menu $menu to delete: $db_ids";
			wp menu item delete $db_ids --path="${mpath}/${path}";
		fi
	done
	# Delete all sidebar entries from the page-widgets sidebar
	widget_ids=$(wp widget list page-widgets --format=ids --path="${mpath}/${path}")
	if [ -n "$widget_ids" ]; then
		echo "Sidebar widgets to delete from page-widgets: $widget_ids";
		wp widget delete $widget_ids --path="${mpath}/${path}";
	fi
done