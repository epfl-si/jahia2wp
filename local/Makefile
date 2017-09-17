#!make
include .env

vars:
	@echo Env-related vars:
	@echo '  WP_ENV=${WP_ENV}'
	@echo '  MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}'
	@echo '  MYSQL_DB_HOST=${MYSQL_DB_HOST}'
	@echo '  MYSQL_SUPER_USER=${MYSQL_SUPER_USER}'
	@echo '  MYSQL_SUPER_PASSWORD=${MYSQL_SUPER_PASSWORD}'

	@echo ''
	@echo 'Wordpress vars:'
	@echo '  SITE_PATH=${SITE_PATH}'
	@echo '  WP_PATH=${WP_PATH}'
	@echo '  WP_URL=${WP_URL}'
	@echo '  WP_TITLE=${WP_TITLE}'
	@echo '  WP_DB_NAME=${WP_DB_NAME}'
	@echo '  MYSQL_WP_USER=${MYSQL_WP_USER}'
	@echo '  MYSQL_WP_PASSWORD=${MYSQL_WP_PASSWORD}'
	@echo '  WP_ADMIN_USER=${WP_ADMIN_USER}'
	@echo '  WP_ADMIN_PASSWORD=${WP_ADMIN_PASSWORD}'
	@echo '  WP_ADMIN_EMAIL=${WP_ADMIN_EMAIL}'

up:
	WP_ENV=${WP_ENV} MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD} docker-compose up -d

run:
	WP_ENV=${WP_ENV} MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD} docker-compose run \
	  -e MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD} \
	  -e MYSQL_DB_HOST=${MYSQL_DB_HOST} \
	  -e SITE_PATH=${SITE_PATH} \
	  -e WP_PATH=${WP_PATH} \
	  -e WP_URL=${WP_URL} \
	  -e WP_TITLE="${WP_TITLE}" \
	  -e WP_DB_NAME=${WP_DB_NAME} \
	  -e MYSQL_WP_USER=${MYSQL_WP_USER} \
	  -e MYSQL_WP_PASSWORD=${MYSQL_WP_PASSWORD} \
	  -e WP_ADMIN_USER=${WP_ADMIN_USER} \
	  -e WP_ADMIN_PASSWORD=${WP_ADMIN_PASSWORD} \
	  -e WP_ADMIN_EMAIL=${WP_ADMIN_EMAIL} \
	  mgmt

clean:
	rm -rf ${WP_PATH}
	mysql -h ${MYSQL_DB_HOST} -u ${MYSQL_SUPER_USER} --password=${MYSQL_SUPER_PASSWORD} -e "DROP DATABASE ${WP_DB_NAME};" 
	mysql -h ${MYSQL_DB_HOST} -u ${MYSQL_SUPER_USER} --password=${MYSQL_SUPER_PASSWORD} -e "DROP USER ${MYSQL_WP_USER};" 

install:
	mysql -h ${MYSQL_DB_HOST} -u ${MYSQL_SUPER_USER} --password=${MYSQL_SUPER_PASSWORD} -e "CREATE USER '${MYSQL_WP_USER}' IDENTIFIED BY '${MYSQL_WP_PASSWORD}';"
	mysql -h ${MYSQL_DB_HOST} -u ${MYSQL_SUPER_USER} --password=${MYSQL_SUPER_PASSWORD} -e "GRANT ALL PRIVILEGES ON ${WP_DB_NAME}.* TO '${MYSQL_WP_USER}'@'%';"
	mkdir -p ${WP_PATH}/htdocs

	wp core download --version=4.8 --path=${WP_PATH}/htdocs
	wp config create --dbname=${WP_DB_NAME} --dbuser=${MYSQL_WP_USER} --dbpass=${MYSQL_WP_PASSWORD} --dbhost=${MYSQL_DB_HOST} --path=${WP_PATH}/htdocs
	wp db create --path=${WP_PATH}/htdocs
	wp --allow-root core install --url=${WP_URL} --title=${WP_TITLE} --admin_user=${WP_ADMIN_USER} --admin_password=${WP_ADMIN_PASSWORD} --admin_email=${WP_ADMIN_EMAIL} --path=${WP_PATH}/htdocs
