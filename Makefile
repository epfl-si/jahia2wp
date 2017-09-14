
clean:
	rm -rf ${WP_PATH}
	mysql -h exopgesrv61.epfl.ch -u ${MYSQL_SUPER_USER} --password=${MYSQL_SUPER_PASSWORD} -e "DROP DATABASE ${WP_DB_NAME};" 
	mysql -h exopgesrv61.epfl.ch -u ${MYSQL_SUPER_USER} --password=${MYSQL_SUPER_PASSWORD} -e "DROP USER ${MYSQL_WP_USER};" 

install:
	mysql -h exopgesrv61.epfl.ch -u ${MYSQL_SUPER_USER} --password=${MYSQL_SUPER_PASSWORD} -e "CREATE USER '${MYSQL_WP_USER}' IDENTIFIED BY '${MYSQL_WP_PASSWORD}';"
	mysql -h exopgesrv61.epfl.ch -u ${MYSQL_SUPER_USER} --password=${MYSQL_SUPER_PASSWORD} -e "GRANT ALL PRIVILEGES ON ${WP_DB_NAME}.* TO '${MYSQL_WP_USER}'@'%';"
	mkdir -p ${WP_PATH}/htdocs
	# cd ${WP_PATH}/htdocs
	wp core download --version=4.8 --path=${WP_PATH}/htdocs
	wp config create --dbname=${WP_DB_NAME} --dbuser=${MYSQL_WP_USER} --dbpass=${MYSQL_WP_PASSWORD} --dbhost=exopgesrv61.epfl.ch --path=${WP_PATH}/htdocs
	wp db create --path=${WP_PATH}/htdocs
	wp --allow-root core install --url=${WP_URL} --title=${WP_TITLE} --admin_user=${WP_ADMIN_USER} --admin_password=${WP_ADMIN_PASSWORD} --admin_email=${WP_ADMIN_EMAIL} --path=${WP_PATH}/htdocs


test:
	./bin/flake8.sh
	pytest --cov=./ src
	coverage html