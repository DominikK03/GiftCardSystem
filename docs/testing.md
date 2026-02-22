# Testing

## Test Pyramid

- Domain: rules and invariants
- Application: handlers and queries
- Infrastructure: projections, HTTP
- Integration: event store, messenger routing

## Run All Tests

```
docker compose exec -T php vendor/bin/phpunit --testdox
```

## Run by Suite

```
docker compose exec -T php vendor/bin/phpunit --testdox tests/GiftCard/Domain/
docker compose exec -T php vendor/bin/phpunit --testdox tests/GiftCard/Application/
docker compose exec -T php vendor/bin/phpunit --testdox tests/GiftCard/Infrastructure/
docker compose exec -T php vendor/bin/phpunit --testdox tests/GiftCard/Integration/
```

## Notes

- HTTP tests require DB tables
- Use migrations for test DB setup
