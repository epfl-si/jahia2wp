bash ./vent-tools/del-posts.sh

export ROOT_WP_DEST=/srv/lboatto/jahia2wp-httpd/htdocs/epfl/

PYTHONIOENCODING="utf-8" python jahia2wp.py migrate-urls ../data/csv/ventilation-local.csv $WP_ENV --root_wp_dest=$ROOT_WP_DEST