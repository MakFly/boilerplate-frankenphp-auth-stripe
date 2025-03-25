PHONY: install dev build build-no-cache down stop workspace

CONTAINER_NAME=boilerplate-symfony-simple

## Docker commands
install:
# sudo sysctl -w vm.max_map_count=262144
	cp .env.example .env
	@docker compose -f compose.override.yaml up -d --remove-orphans
	@docker exec -it ${CONTAINER_NAME} composer install
	sudo chown -R ${USER}:${USER} .
	rm -rf var/log/*
	@docker exec -it ${CONTAINER_NAME} php bin/console c:c
	@docker exec -it ${CONTAINER_NAME} php bin/console lexik:jwt:generate-keypair --overwrite
	@docker exec -it ${CONTAINER_NAME} php bin/console d:m:m
	@docker exec -it ${CONTAINER_NAME} php bin/console d:f:l
	@docker exec -it ${CONTAINER_NAME} php bin/console d:d:c --env=test
	@docker exec -it ${CONTAINER_NAME} php bin/console d:m:m --env=test

dev:
# sudo sysctl -w vm.max_map_count=262144
	@docker compose -f compose.override.yaml up -d --remove-orphans
	rm -rf var/log/*
	sleep 2
	@docker exec -it ${CONTAINER_NAME} bash -c 'if ! php bin/console doctrine:database:exists --env=test 2>/dev/null; then php bin/console doctrine:database:create --env=test --if-not-exists; else echo "Test database already exists"; fi'
	@docker exec -it ${CONTAINER_NAME} php bin/console c:c

build:
	@docker compose -f compose.override.yaml build

build-no-cache:
	@docker compose build --no-cache

restart:
	@docker compose restart webapp

pull:
	@docker compose -f compose.yaml pull

prod:
	@docker compose -f compose.yaml up -d --remove-orphans

# clear cache
clear:
	symfony console c:c

charge-database:
	@docker exec -it ${CONTAINER_NAME} php bin/console d:m:m --no-interaction
	@docker exec -it ${CONTAINER_NAME} php bin/console d:f:l --no-interaction

create-migration:
	@docker exec -it ${CONTAINER_NAME} php bin/console make:migration

diff-migration:
	@docker exec -it ${CONTAINER_NAME} php bin/console doctrine:migrations:diff

migrate-run:
	@docker exec -it ${CONTAINER_NAME} php bin/console d:m:m

stop:
	@docker compose stop

down:
	@docker compose down --remove-orphans --volumes

workspace:
	@docker exec -it ${CONTAINER_NAME} bash

workspace-prod:
	@docker exec -ti boilerplate-symfony-production bash

caddyfile:
	@docker exec -it ${CONTAINER_NAME} cat /etc/caddy/Caddyfile

############ Tests #######################################
test-prepare:
# @docker exec -it ${CONTAINER_NAME} php bin/console d:d:d --force --env=test --no-interaction
# @docker exec -it ${CONTAINER_NAME} php bin/console d:d:c --env=test --no-interaction
	@docker exec -it ${CONTAINER_NAME} php bin/console d:m:m --env=test --no-interaction
# @docker exec -it ${CONTAINER_NAME} php bin/console d:f:l --env=test --no-interaction

test: test-prepare
	@docker exec -it ${CONTAINER_NAME} ./vendor/bin/pest

test-coverage: test-prepare
	@docker exec -it ${CONTAINER_NAME} ./vendor/bin/pest --coverage

test-unit: test-prepare
	@docker exec -it ${CONTAINER_NAME} ./vendor/bin/pest --group=unit

test-feature: test-prepare
	@docker exec -it ${CONTAINER_NAME} ./vendor/bin/pest --group=feature

############ Logs ########################################
logs:
	@docker compose logs -f webapp

stan:
	@docker exec -it ${CONTAINER_NAME} ./vendor/bin/phpstan analyse src --memory-limit=1G