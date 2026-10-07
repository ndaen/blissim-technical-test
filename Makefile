# Raccourcis pour lancer le projet. `make help` liste les commandes.
# APP_PORT et DB_PORT sont surchargeables : `make up APP_PORT=8081`

COMPOSE_FILES := -f docker/docker-compose.yml
ifneq ($(wildcard .env.local),)
COMPOSE_FILES += -f docker/docker-compose.local.yml
endif
DC := docker compose --env-file .env $(COMPOSE_FILES)

.DEFAULT_GOAL := help
.PHONY: help up down logs shell console db-shell db-reset test stan

help: ## Affiche cette aide
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-10s %s\n", $$1, $$2}'

up: ## Construit et démarre l'application et la base
	$(DC) up -d --build

down: ## Arrête les conteneurs (la base est conservée)
	$(DC) down

logs: ## Suit les logs du serveur PHP
	$(DC) logs -f php

shell: ## Ouvre un shell dans le conteneur PHP
	$(DC) exec php bash

console: ## Lance bin/console : make console c="debug:router"
	$(DC) exec php php bin/console $(c)

db-shell: ## Ouvre le client MySQL sur la base blissim
	$(DC) exec db mysql -uapp -papp blissim

db-reset: ## Efface la base et rejoue blissim.sql
	$(DC) down -v
	$(DC) up -d

test: ## Lance PHPUnit
	$(DC) exec -e APP_ENV=test php vendor/bin/phpunit

stan: ## Lance PHPStan
	$(DC) exec php vendor/bin/phpstan analyse
