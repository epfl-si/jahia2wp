#!make
# ran in mgmt container

test:
	flake8 --max-line-length=120 src
	pytest --cov=./ src
	coverage html

vars:
	make -C local vars
