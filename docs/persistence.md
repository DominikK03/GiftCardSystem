# Persistence

## Event Store

- Table: events
- Implemented by Broadway DBAL event store
- Primary key: id (sequence)
- Unique key: (uuid, playhead)

Schema summary:
- id: SERIAL
- uuid: UUID
- playhead: INT
- metadata: JSON
- payload: JSON
- recorded_on: TIMESTAMP
- type: VARCHAR

## Read Model

- Table: gift_cards_read
- Updated by GiftCardReadModelProjection
- Used by GetGiftCardQuery

Schema summary:
- id (UUID primary key)
- balance_amount, balance_currency
- initial_amount, initial_currency
- status
- created_at, updated_at
- activated_at, suspended_at, cancelled_at, expired_at, depleted_at
- expires_at
- suspension_duration

## Migrations

- Managed by Doctrine Migrations
- Migration entry: migrations/Version20260101000000.php

## Rebuild Read Model

Use this when projections are out of sync or after new projection logic.

```
bin/console app:gift-card:rebuild-read-model --truncate
```
