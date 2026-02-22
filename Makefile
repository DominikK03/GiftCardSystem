# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc test sql-load wait-db initialize initialize-test test-preflight test-db-reset test-sql-load-test test-migrate-test test-prepare

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

start: build up ## Build and start the containers

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the FrankenPHP container
	@$(PHP_CONT) sh

bash: ## Connect to the FrankenPHP container via bash so up and down arrows go to previous commands
	@$(PHP_CONT) bash

test: test-prepare ## Start tests with phpunit, pass the parameter "c=" to add options to phpunit, example: make test c="--group e2e --stop-on-failure"
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -T php vendor/bin/phpunit $(c)


## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

sql-load: ## Load SQL bootstrap files from ./sql into PostgreSQL container
	@set -e; \
	for file in sql/001_create_events_table.sql sql/002_create_messenger_tables.sql sql/003_create_gift_cards_read_table.sql; do \
		echo "Importing $$file"; \
		cat "$$file" | $(DOCKER_COMP) exec -T database psql -v ON_ERROR_STOP=1 -U $${POSTGRES_USER:-app} -d $${POSTGRES_DB:-app}; \
	done

wait-db: ## Wait until PostgreSQL container is ready
	@echo "Waiting for PostgreSQL..."
	@until $(DOCKER_COMP) exec -T database pg_isready -U $${POSTGRES_USER:-app} -d $${POSTGRES_DB:-app} >/dev/null 2>&1; do \
		sleep 1; \
	done
	@echo "PostgreSQL is ready"

initialize: ## Fully initialize local environment (containers, deps, SQL, migrations, admin user)
	@$(DOCKER_COMP) up -d --build
	@$(MAKE) wait-db
	@$(DOCKER_COMP) exec php composer install --prefer-dist --no-interaction
	@$(MAKE) sql-load
	@$(DOCKER_COMP) exec php bin/console doctrine:migrations:migrate --no-interaction
	@$(DOCKER_COMP) exec php bin/console app:create-admin --email=admin@giftcard.pl --password=admin123 --role=OWNER
	@$(DOCKER_COMP) exec php bin/console app:load-fixtures --force
	@$(DOCKER_COMP) exec node npm install
	@$(DOCKER_COMP) exec -d node npm run dev
	@echo "Initialization finished"

initialize-test: ## Prepare containers and dependencies for deterministic test runs
	@$(DOCKER_COMP) up -d --build
	@$(MAKE) wait-db
	@$(DOCKER_COMP) exec -T php composer install --prefer-dist --no-interaction
	@$(MAKE) test-prepare
	@echo "Test initialization finished"

test-preflight: ## Ensure required services are running and env basics are valid
	@$(DOCKER_COMP) up -d database php rabbitmq redis
	@$(MAKE) wait-db
	@$(DOCKER_COMP) exec -T php php -r "exit(strlen((string) getenv('MERCURE_JWT_SECRET')) >= 32 ? 0 : 1);" \
		|| (echo "MERCURE_JWT_SECRET must be at least 32 characters"; exit 1)

test-db-reset: ## Recreate app_test database
	@$(DOCKER_COMP) exec -T database psql -U $${POSTGRES_USER:-app} -d postgres -v ON_ERROR_STOP=1 \
		-c "DROP DATABASE IF EXISTS app_test WITH (FORCE);" \
		-c "CREATE DATABASE app_test;"

test-sql-load-test: ## Load bootstrap SQL files into app_test database
	@set -e; \
	for file in sql/001_create_events_table.sql sql/002_create_messenger_tables.sql sql/003_create_gift_cards_read_table.sql; do \
		echo "Importing $$file into app_test"; \
		cat "$$file" | $(DOCKER_COMP) exec -T database psql -v ON_ERROR_STOP=1 -U $${POSTGRES_USER:-app} -d app_test; \
	done

test-migrate-test: ## Run Doctrine migrations against app_test
	@$(DOCKER_COMP) exec -T -e APP_ENV=test -e DATABASE_URL="postgresql://$${POSTGRES_USER:-app}:$${POSTGRES_PASSWORD:-secret321}@database:5432/app_test?serverVersion=16&charset=utf8" \
		php bin/console doctrine:migrations:migrate --no-interaction

test-prepare: test-preflight test-db-reset test-sql-load-test test-migrate-test ## Full deterministic test bootstrap
	@$(DOCKER_COMP) exec -T php bin/console cache:clear --env=test --no-warmup || true
