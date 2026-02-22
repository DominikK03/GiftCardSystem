# Domain Model

## Aggregate: GiftCard

Core state:
- id
- balance
- initialAmount
- status
- createdAt, expiresAt
- activatedAt, suspendedAt, cancelledAt, expiredAt, depletedAt
- suspensionDurationSeconds

## Status Lifecycle

- INACTIVE -> ACTIVE
- ACTIVE -> SUSPENDED -> ACTIVE
- ACTIVE -> CANCELLED
- ACTIVE -> EXPIRED
- ACTIVE -> DEPLETED

## Invariants

- Amounts are integers in smallest currency unit
- Currency must be ISO 4217
- Cannot redeem/adjust/decrease below zero
- Cannot activate/redeem if expired
- Cannot expire before expiration date
- Reactivation requires suspension duration

## Commands and Intent

- CreateCommand: create new card
- ActivateCommand: activate inactive card
- RedeemCommand: redeem amount
- SuspendCommand: temporary suspension
- ReactivateCommand: resume card
- CancelCommand: invalidate card
- AdjustBalanceCommand: add or subtract amount
- DecreaseBalanceCommand: subtract only
- ExpireCommand: mark expired

## Events

- GiftCardCreated
- GiftCardActivated
- GiftCardRedeemed
- GiftCardSuspended
- GiftCardReactivated
- GiftCardCancelled
- GiftCardExpired
- GiftCardDepleted
- GiftCardBalanceAdjusted
- GiftCardBalanceDecreased

## Value Objects

- GiftCardId: UUID wrapper with validation
- Money: amount + currency with currency mismatch protection
