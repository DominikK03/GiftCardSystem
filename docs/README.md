# GiftCard Documentation

This folder contains the full project documentation for the GiftCard system.

## Index

- overview.md - Purpose, scope, goals, and constraints
- architecture.md - Layering, boundaries, and component map
- domain.md - Domain model, invariants, commands, events
- api.md - HTTP endpoints, validation, errors, examples
- persistence.md - Event store, read model, migrations
- messaging.md - Messenger routing and async processing
- testing.md - Test strategy and how to run tests
- operations.md - Running locally, workers, rebuilds
- glossary.md - Shared terms

## Quick Start

- Setup: ../SETUP.md
- API base path: /api/gift-cards
- Rebuild read model: bin/console app:gift-card:rebuild-read-model --truncate
