# GiftCard

System zarządzania kartami podarunkowymi oparty o Symfony, PostgreSQL, RabbitMQ, Redis i FrankenPHP.

## Wymagania

- Docker Engine + Docker Compose (plugin v2)

## Szybki start (lokalnie)

Najprościej:

```bash
make initialize
```

Ta komenda wykona:

- start kontenerów,
- oczekiwanie na gotowość PostgreSQL,
- `composer install` w kontenerze `php`,
- import plików SQL z `sql/001`, `sql/002`, `sql/003`,
- migracje Doctrine,
- utworzenie admina `admin@giftcard.pl` z hasłem `secret321`.
- załadowanie fixture (`app:load-fixtures --force`),
- `npm install` w kontenerze `node`,
- uruchomienie `npm run dev` w kontenerze `node`.

Alternatywnie ręcznie:

1. Uruchom środowisko developerskie:

```bash
APP_ENV=dev docker compose up -d --build
```

2. Zainstaluj zależności PHP:

```bash
docker compose exec php composer install
```

3. Wstrzyknij SQL bootstrap:

```bash
make sql-load
```

Jeśli nie masz `make`, użyj:

```bash
cat sql/001_create_events_table.sql | docker compose exec -T database psql -v ON_ERROR_STOP=1 -U app -d app
cat sql/002_create_messenger_tables.sql | docker compose exec -T database psql -v ON_ERROR_STOP=1 -U app -d app
cat sql/003_create_gift_cards_read_table.sql | docker compose exec -T database psql -v ON_ERROR_STOP=1 -U app -d app
```

4. Uruchom migracje:

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

5. Utwórz użytkownika do panelu admina:

```bash
docker compose exec php bin/console app:create-admin --email=admin@giftcard.pl --password=secret321 --role=OWNER
```

6. Załaduj fixtures:

```bash
docker compose exec php bin/console app:load-fixtures --force
```

7. Zainstaluj zależności frontend:

```bash
docker compose exec node npm install
```

8. Uruchom frontend watch:

```bash
docker compose exec -d node npm run dev
```

9. Otwórz aplikację:

- Aplikacja: `http://localhost`
- Panel logowania admin: `http://localhost/admin/login`
- RabbitMQ Management: `http://localhost:15672`

## Dane dostępowe domyślne (lokalnie)

- PostgreSQL: `app / secret321`, baza `app`
- RabbitMQ: `app / secret321`, vhost `app`

## Fixtures (opcjonalnie)

Komenda usuwa dane i ładuje przykładowe rekordy:

```bash
docker compose exec php bin/console app:load-fixtures --force
```

Tworzeni są m.in. użytkownicy:

- `admin@giftcard.pl / admin123`
- `support@giftcard.pl / admin123`

## Testy

Najprościej (bez ręcznej diagnostyki środowiska):

```bash
make initialize-test
make test
```

`make test` automatycznie:

- uruchamia wymagane kontenery,
- czeka na gotowość PostgreSQL,
- odtwarza bazę `app_test`,
- ładuje SQL bootstrap do `app_test`,
- uruchamia migracje testowe,
- czyści cache testowy,
- uruchamia PHPUnit.

Jeśli nie używasz `make`, odpowiednik:

```bash
docker compose up -d database php rabbitmq redis
docker compose exec -T database psql -U app -d postgres -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS app_test WITH (FORCE);" -c "CREATE DATABASE app_test;"
cat sql/001_create_events_table.sql | docker compose exec -T database psql -v ON_ERROR_STOP=1 -U app -d app_test
cat sql/002_create_messenger_tables.sql | docker compose exec -T database psql -v ON_ERROR_STOP=1 -U app -d app_test
cat sql/003_create_gift_cards_read_table.sql | docker compose exec -T database psql -v ON_ERROR_STOP=1 -U app -d app_test
docker compose exec -T -e APP_ENV=test -e DATABASE_URL="postgresql://app:secret321@database:5432/app_test?serverVersion=16&charset=utf8" php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T php vendor/bin/phpunit --testdox
```

## Uruchomienie produkcyjne (Docker Compose)

1. Ustaw sekrety jako zmienne środowiskowe:

```bash
export APP_SECRET='wlasny_app_secret'
export CADDY_MERCURE_JWT_SECRET='wlasny_mercure_jwt_secret'
export POSTGRES_PASSWORD='wlasne_haslo_postgres'
export RABBITMQ_DEFAULT_PASS='wlasne_haslo_rabbitmq'
```

2. Uruchom stack produkcyjny:

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
```

3. Uruchom migracje:

```bash
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console doctrine:migrations:migrate --no-interaction
```

## Przydatne komendy

```bash
docker compose ps
docker compose logs -f php
docker compose logs -f worker
docker compose down
docker compose down -v
```
