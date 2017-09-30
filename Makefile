#!make
# ran in mgmt container

test:
	flake8 --max-line-length=120 src
	pytest --cov=./ src
	coverage html

test-travis:
	. /srv/${WP_ENV}/venv/bin/activate \
	  && export PYTHONPATH=/srv/${WP_ENV}/jahia2wp/src \
	  && flake8 --max-line-length=120 src \
	  && pytest --cov=./ src \
	  && codecov -t $CODECOV_TOKEN

vars:
	make -C local vars
