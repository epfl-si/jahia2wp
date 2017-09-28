#!make

include local/.env
export

test:
	./bin/flake8.sh
	pytest --cov=./ src
	coverage html
