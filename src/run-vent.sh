bash ./vent-tools/del-posts.sh

PYTHONIOENCODING="utf-8" python jahia2wp.py migrate-urls ../data/csv/ventilation-local.csv $WP_ENV --root_wp_dest=/srv/hmuriel/jahia2wp-httpd/htdocs/epfl/,/srv/hmuriel/jahia2wp-httpd/htdocs/inside
