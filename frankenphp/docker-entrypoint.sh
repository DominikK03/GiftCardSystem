#!/bin/sh
set -e

warn_init_step() {
	echo "[entrypoint][warn] $1"
}

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Install the project the first time PHP is started
	# After the installation, the following block can be deleted
	if [ ! -f composer.json ]; then
		rm -Rf tmp/
		composer create-project "symfony/skeleton $SYMFONY_VERSION" tmp --stability="$STABILITY" --prefer-dist --no-progress --no-interaction --no-install

		cd tmp
		cp -Rp . ..
		cd -
		rm -Rf tmp/

		composer require "php:>=$PHP_VERSION" runtime/frankenphp-symfony
		composer config --json extra.symfony.docker 'true'

		if grep -q ^DATABASE_URL= .env; then
			echo 'To finish the installation please press Ctrl+C to stop Docker Compose and run: docker compose up --build --wait'
			sleep infinity
		fi
	fi

	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		if ! composer install --prefer-dist --no-progress --no-interaction; then
			warn_init_step "composer install failed, continuing container startup"
		fi
	fi

	# Display information about the current project
	# Or about an error in project initialization
	if ! php bin/console -V; then
		warn_init_step "php bin/console -V failed, continuing container startup"
	fi

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		DATABASE_READY=0
		DATABASE_ERROR=""
		while [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -gt 0 ]; do
			if DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); then
				DATABASE_READY=1
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $DATABASE_READY -eq 0 ]; then
			warn_init_step "database is not up or not reachable"
			echo "$DATABASE_ERROR"
		else
			echo 'The database is now ready and reachable'
		fi

		if [ "$(find ./migrations -iname '*.php' -print -quit)" ]; then
			if ! php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing; then
				warn_init_step "automatic migrations failed, continuing container startup"
			fi
		fi
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
