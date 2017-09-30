#!make
# ran in mgmt container

test:
	docker exec mgmt make -C /srv/$$WP_ENV/jahia2wp test-raw

test-raw:
	. /srv/${WP_ENV}/venv/bin/activate \
	  && export PYTHONPATH=/srv/${WP_ENV}/jahia2wp/src \
	  && flake8 --max-line-length=120 src \
	  && pytest --cov=./ src \
	  && coverage html

test-travis:
	. /srv/${WP_ENV}/venv/bin/activate \
	  && export PYTHONPATH=/srv/${WP_ENV}/jahia2wp/src \
	  && flake8 --max-line-length=120 src \
	  && pytest --cov=./ src \
	  && codecov -t ${CODECOV_TOKEN}

vars:
	make -C local vars
