#!make

include local/.env
export

dev:
	python src/generator/tests/test_generator.py

test:
	./bin/flake8.sh
	pytest --cov=./ src
	coverage html

vars:
	make -C local vars
