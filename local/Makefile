#!make
include .env

vars:
	@echo 'Environment-related vars:'
	@echo '  WP_ENV=${WP_ENV}'
	
	@echo ''
	@echo DB-related vars:
	@echo '  MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}'
	@echo '  MYSQL_DB_HOST=${MYSQL_DB_HOST}'
	@echo '  MYSQL_SUPER_USER=${MYSQL_SUPER_USER}'
	@echo '  MYSQL_SUPER_PASSWORD=${MYSQL_SUPER_PASSWORD}'

	@echo ''
	@echo 'Wordpress-related vars:'
	@echo '  WP_VERSION=${WP_VERSION}'
	@echo '  WP_ADMIN_USER=${WP_ADMIN_USER}'
	@echo '  WP_ADMIN_EMAIL=${WP_ADMIN_EMAIL}'

up:
	@WP_ENV=${WP_ENV} \
		MYSQL_DB_HOST=${MYSQL_DB_HOST} \
		MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD} \
		docker-compose up -d

exec:
	@docker exec --user www-data -it  \
	  -e WP_ENV=${WP_ENV} \
	  -e MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD} \
	  -e MYSQL_DB_HOST=${MYSQL_DB_HOST} \
	  mgmt bash -l

down:
	docker-compose down

bootstrap-local:
	cp .env.sample .env
	sudo chown -R `whoami`:33 ..
	sudo chmod -R g+w ..
ifdef WP_ENV
	@echo "WP_ENV already set to $(WP_ENV)"
	make up
else
	echo " \
export WP_ENV=$(ENV)" >> ~/.bashrc
	WP_ENV=$(ENV) make up
endif
	@echo ""
	@echo "Done with your local env. You can now" 
	@if test -z "${WP_ENV}"; then echo "    $ source ~/.bashrc (to update your environment with WP_ENV value)"; fi
	@echo "    $ make exec        (to connect into your contanier)"