all: down bootstrap up logs

down:
	docker-compose down

up:
	docker-compose up -d
	docker exec wp1 /usr/local/bin/activate_cache.sh
	docker exec wp2 /usr/local/bin/activate_cache.sh

install-wp:
	docker exec wp1 /usr/local/bin/install_wp.sh
	docker exec wp2 /usr/local/bin/install_wp.sh

bootstrap:
	docker-compose pull
	docker-compose build
	docker volume create --name=pmcconfig || true

logs: up
	docker-compose logs --tail 10 -f
