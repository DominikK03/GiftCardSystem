# Overview

GiftCard is a domain-driven system for managing gift cards with full auditability.
All state changes are stored as domain events, while query performance is achieved
using a separate read model.

## Goals

- Correctness of business rules (invariants enforced in domain)
- Full audit trail (event sourcing)
- Fast reads (CQRS read model)
- Async processing for commands and events

## Non-Goals

- Payment processing, checkout, or user accounts
- Admin UI or backoffice workflows

## Key Characteristics

- Event-sourced write model
- Read model projection for queries
- Asynchronous command/event handling
- Explicit ports between layers

## Tech Stack

- PHP 8.4, Symfony 7.3
- Broadway (event store)
- Symfony Messenger (async transports)
- Doctrine ORM (read model)
- PostgreSQL, RabbitMQ

## Code Map

- Domain: src/Domain/GiftCard
- Application: src/Application/GiftCard
- Infrastructure: src/Infrastructure/GiftCard
- Interface (HTTP): src/Interface/Http
