#!/bin/bash

if grep --quiet memcached_servers wp-config.php; then
  echo "Config already adjusted with memcached, email"
  exit 0
else
  echo "Customizing wp-config.php, enabling cache"
fi

cp /var/object-cache.php /var/www/html/wp-content/
chown -R www-data:www-data /var/www/html

sed -i "s/<?php/<?php\n\ndefine( 'WP_CACHE_KEY_SALT', '${WORDPRESS_DB_NAME}' );\ndefine( 'WP_CACHE', true );\n\n\$memcached_servers = array(\n    'default' => array(\n        'memcached:11211'\n    )\n);/" /var/www/html/wp-config.php
