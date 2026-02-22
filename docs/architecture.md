# Architecture

## Layered Structure

- Domain: entities, value objects, events, and invariants
- Application: commands, queries, handlers, ports, views
- Infrastructure: persistence, event store, messaging
- Interface: HTTP controllers and request DTOs

## Dependencies

- Domain does not depend on other layers
- Application depends on Domain and Ports
- Infrastructure implements Ports and depends on Application/Domain
- Interface depends on Application for use cases

## Component Map

- GiftCard aggregate: src/Domain/GiftCard/Aggregate/GiftCard.php
- Commands/Handlers: src/Application/GiftCard/Command, src/Application/GiftCard/Handler
- Queries/Views: src/Application/GiftCard/Query, src/Application/GiftCard/View
- Read model: src/Application/GiftCard/ReadModel
- Read model projection: src/Infrastructure/GiftCard/Persistence/ReadModel/GiftCardReadModelProjection.php
- Event store: Broadway DBAL in src/Infrastructure/GiftCard/EventSourcing/Broadway
- HTTP: src/Interface/Http/Controller/GiftCardController.php

## Data Flow

1. HTTP controller validates input and dispatches command
2. Command handler loads aggregate and applies domain method
3. Aggregate emits domain events
4. Event store persists events
5. Event bus forwards events to Messenger
6. Projection updates read model

## CQRS & Event Sourcing

- Write model: GiftCard aggregate, event sourced
- Read model: gift_cards_read table, updated by projection
- Queries use read model only

## Decisions

- Commands are async (except create) to isolate write latency
- Domain events are async to decouple projections
- Read model is denormalized for fast reads
