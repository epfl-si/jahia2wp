#!make
# Default values, can be overridden either on the command line of make
# or in .env
WP_ENV ?= your-env
WP_PORT_HTTP ?= 80
WP_PORT_HTTPS ?= 443

check-env:
ifeq ($(wildcard .env),)
	@echo "Please create your .env file first, from .env.sample"
	@exit 1
else
include .env
endif

test: check-env
	docker exec mgmt make -C /srv/$$WP_ENV/jahia2wp test-raw

test-raw: check-env
	. /srv/${WP_ENV}/venv/bin/activate \
	  && export PYTHONPATH=/srv/${WP_ENV}/jahia2wp/src \
	  && flake8 --max-line-length=120 src \
	  && pytest --cov=./ src \
	  && coverage html

test-travis: check-env
	. /srv/${WP_ENV}/venv/bin/activate \
	  && export PYTHONPATH=/srv/${WP_ENV}/jahia2wp/src \
	  && flake8 --max-line-length=120 src \
	  && pytest --cov=./ src \
	  && codecov
	bash -c 'bash <(curl -s https://codecov.io/bash)'

vars: check-env
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
	@echo '  WP_PORT_HTTP=${WP_PORT_HTTP}'
	@echo '  WP_PORT_HTTPS=${WP_PORT_HTTPS}'

up: check-env
	@WP_ENV=${WP_ENV} \
		MYSQL_DB_HOST=${MYSQL_DB_HOST} \
		MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD} \
		WP_PORT_HTTP=${WP_PORT_HTTP} \
		WP_PORT_HTTPS=${WP_PORT_HTTPS} \
		docker-compose up -d

exec: check-env
	@docker exec --user www-data -it  \
	  -e WP_ENV=${WP_ENV} \
	  -e MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD} \
	  -e MYSQL_DB_HOST=${MYSQL_DB_HOST} \
	  mgmt bash -l

down: check-env
	@WP_PORT_HTTP=${WP_PORT_HTTP} \
	 WP_PORT_HTTPS=${WP_PORT_HTTPS} \
	 docker-compose down

bootstrap-local:
	cp .env.sample .env
	cp etc/.bash_history.sample etc/.bash_history
	sudo chown -R `whoami`:33 .
	sudo chmod -R g+w .
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

bootstrap-mgmt: check-env
	cd .. \
	  && virtualenv -p `which python3` venv
	. /srv/${WP_ENV}/venv/bin/activate \
	  && export PYTHONPATH=/srv/${WP_ENV}/jahia2wp/src \
	  && pip install -r requirements/local.txt