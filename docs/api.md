# API

Base path: /api/gift-cards
Content-Type: application/json

## Common Validation

- id must be UUID
- currency must be ISO 4217
- dates must be ISO-8601 (example: 2025-01-01T10:00:00+00:00)
- invalid JSON returns 400

## Error Shape

Errors return 400/500 with:
```json
{ "error": "message" }
```

## Endpoints

### POST /create

Body:
```json
{ "amount": 1000, "currency": "PLN", "expiresAt": "2026-01-01T10:00:00+00:00" }
```

Response 201:
```json
{ "message": "Gift card created successfully", "id": "...", "status": "created" }
```

### POST /{id}/redeem

Body:
```json
{ "amount": 100, "currency": "PLN" }
```

Response 202:
```json
{ "message": "Gift card redeem command dispatched", "id": "...", "status": "pending" }
```

### POST /{id}/activate

Body (optional):
```json
{ "activatedAt": "2025-01-01T10:00:00+00:00" }
```

### POST /{id}/suspend

Body:
```json
{ "reason": "Compliance", "suspendedAt": "2025-01-01T10:00:00+00:00", "suspensionDurationSeconds": 3600 }
```

### POST /{id}/reactivate

Body (optional):
```json
{ "reason": "Manual", "reactivatedAt": "2025-01-02T10:00:00+00:00" }
```

### POST /{id}/cancel

Body (optional):
```json
{ "reason": "Customer request", "cancelledAt": "2025-01-02T10:00:00+00:00" }
```

### POST /{id}/adjust-balance

Body:
```json
{ "amount": -500, "currency": "PLN", "reason": "Refund", "adjustedAt": "2025-01-02T10:00:00+00:00" }
```

### POST /{id}/decrease-balance

Body:
```json
{ "amount": 200, "currency": "PLN", "reason": "Correction", "decreasedAt": "2025-01-02T10:00:00+00:00" }
```

### POST /{id}/expire

Body (optional):
```json
{ "expiredAt": "2025-12-31T23:59:59+00:00" }
```

### GET /{id}

Response 200:
```json
{
  "id": "...",
  "balance": { "amount": 1000, "currency": "PLN", "formatted": "10.00 PLN" },
  "initialAmount": { "amount": 1000, "currency": "PLN", "formatted": "10.00 PLN" },
  "status": "ACTIVE",
  "expiresAt": null,
  "createdAt": "2025-01-01T10:00:00+00:00",
  "activatedAt": "2025-01-02T10:00:00+00:00",
  "updatedAt": "2025-01-02T10:00:00+00:00"
}
```

### GET /{id}/history

Response 200:
```json
{
  "giftCardId": "...",
  "totalEvents": 1,
  "history": [
    {
      "event": { "type": "GiftCardCreated", "number": 1, "occurredAt": "...", "payload": { } },
      "stateAfterEvent": { "status": "INACTIVE", "balance": { "amount": 1000, "currency": "PLN", "formatted": "10.00 PLN" } }
    }
  ]
}
```
