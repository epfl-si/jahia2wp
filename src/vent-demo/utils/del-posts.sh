#!/bin/bash


# Take the list of paths passed to the script
site_paths="$@"

echo "Running delete ***ALL*** content (posts, media, sidebar, menu) starting at path $site_paths";

for mpath in $site_paths
do
	for path in $(find $mpath -iname "wp-config.php" -exec dirname {} \;)
	do
		if ! wp core is-installed --path="${path}" 2>/dev/null; then
			echo "${path} is not a valid install, skipping...";
			continue
		fi
		# Continue with the valid installs only
		ids=$(wp post list --post_type='page' --format=ids --path="${path}")
		if [ -n "$ids" ]; then
			echo "ids for $path: ${ids[@]}";
			wp post delete --force --path=$path $ids
		else
			echo "No posts for $path"
		fi
		# Delete all attachments
		media=$(wp post list --post_type='attachment' --format=ids --path="${path}")
		if [ -n "$media" ]; then
			echo "Deleting media / attachments: $media";
			wp post delete $media --path="${path}";
		fi
		# Delete any remaining menu entries
		menu_list=$(wp menu list --fields=term_id --format=ids --path="${path}")
		for menu in $menu_list
		do
			db_ids=$(wp menu item list $menu --fields=db_id --format=ids --path="${path}")
			if [ -n "$db_ids" ]; then
				echo "menu entries from menu $menu to delete: $db_ids";
				wp menu item delete $db_ids --path="${path}";
			fi
		done
		# Delete all sidebar entries from the page-widgets sidebar
		widget_ids=$(wp widget list page-widgets --format=ids --path="${path}")
		if [ -n "$widget_ids" ]; then
			echo "Sidebar widgets to delete from page-widgets: $widget_ids";
			wp widget delete $widget_ids --path="${path}";
		fi
	done
done