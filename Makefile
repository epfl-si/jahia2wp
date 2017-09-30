#!make

include local/.env
export

dev:
	python src/generator/tests/test_generator.py

test:
	flake8 --max-line-length=120 src
	pytest --cov=./ src
	coverage html

vars:
	make -C local vars
