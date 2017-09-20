#!make

test:
	./bin/flake8.sh
	pytest --cov=./ src
	coverage html
