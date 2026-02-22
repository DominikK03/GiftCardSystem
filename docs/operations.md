# Operations

## Local Run

```
docker compose up -d
bin/console doctrine:migrations:migrate --no-interaction
```

## Workers

```
bin/console messenger:consume async -vv
bin/console messenger:consume async_events -vv
```

## Health

- GET /api/gift-cards/health

## Rebuild Read Model

```
bin/console app:gift-card:rebuild-read-model --truncate
```

## Common Commands

- Inspect routes: bin/console debug:router
- Messenger config: bin/console debug:messenger
- Doctrine connection: bin/console dbal:run-sql "SELECT version();"
