# Messaging

## Transports

- async: command queue (RabbitMQ)
- async_events: domain event queue (RabbitMQ)
- failed: Doctrine failed transport

## Routing

- CreateCommand uses sync transport
- All other commands -> async
- All domain events -> async_events

## Retry Strategy

- Commands: 3 retries, exponential backoff
- Events: 3 retries, exponential backoff

## Workers

```
bin/console messenger:consume async -vv
bin/console messenger:consume async_events -vv
```

## Failure Handling

- Failed messages land in Doctrine failed transport
- Inspect with: bin/console messenger:failed:show
- Retry with: bin/console messenger:failed:retry
