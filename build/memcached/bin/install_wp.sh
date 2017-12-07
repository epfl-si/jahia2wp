#!/bin/bash

wp core install --allow-root \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --url=http://localhost:${WORDPRESS_PORT} \
    --title=${WORDPRESS_DB_NAME}