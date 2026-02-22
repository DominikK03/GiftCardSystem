# Code Review & Analysis - Transformacja do Multi-Tenant SaaS

**Data:** 2025-12-27
**Cel:** Analiza istniejącego kodu i przygotowanie strategii migracji do wielokliencowego systemu SaaS

---

## 📊 Executive Summary

Projekt jest **bardzo dobrze zaprojektowany** zgodnie z zasadami Clean Architecture, DDD, CQRS i Event Sourcing. Architektura jest solidna i gotowa do rozbudowy o funkcjonalności multi-tenant SaaS.

**Ocena ogólna:** ⭐⭐⭐⭐⭐ (5/5)

**Co działa świetnie:**
- ✅ Clean Architecture (wyraźna separacja warstw)
- ✅ Event Sourcing z Broadway (dojrzała implementacja)
- ✅ CQRS (Command/Query separation)
- ✅ Asynchroniczna komunikacja (RabbitMQ + Symfony Messenger)
- ✅ Read Model Projection (asynchroniczna)
- ✅ Domain Events routing do Messenger
- ✅ Solidne Value Objects
- ✅ OpenAPI dokumentacja
- ✅ Testy jednostkowe i integracyjne

**Co wymaga dodania:**
- ❌ Multi-tenancy (tenant_id, TenantContext, RLS)
- ❌ HMAC authentication
- ❌ Consumer Portal (CardHolder context)
- ❌ Admin Panel
- ❌ Email notifications
- ❌ Webhooks
- ❌ Rate limiting
- ❌ GDPR compliance
- ❌ Audit logging

---

## 🏗️ Analiza Architektury

### Struktura Warstw

```
src/
├── Domain/           ⭐ DOSKONAŁA separacja logiki biznesowej
│   └── GiftCard/
│       ├── Aggregate/      GiftCard - event sourced aggregate
│       ├── Event/          10 domain events (wszystkie potrzebne)
│       ├── ValueObject/    GiftCardId, Money (immutable, validated)
│       ├── Enum/           GiftCardStatus
│       ├── Exception/      10 domain exceptions (specific)
│       └── Port/           GiftCardRepository interface
│
├── Application/      ⭐ CQRS wzorce dobrze zaimplementowane
│   └── GiftCard/
│       ├── Command/        9 commands (primitives only - correct!)
│       ├── Handler/        9 command handlers + 2 query handlers
│       ├── Query/          GetGiftCard, GetGiftCardHistory
│       ├── ReadModel/      GiftCardReadModel (denormalized)
│       ├── View/           GiftCardView, GiftCardHistoryView
│       ├── Persister/      GiftCardPersister (adapter)
│       └── Provider/       GiftCardProvider (adapter)
│
├── Infrastructure/   ⭐ Broadway + Messenger + Read Model
│   └── GiftCard/
│       ├── EventSourcing/
│       │   └── Broadway/
│       │       ├── GiftCardRepositoryBroadway (adapter)
│       │       └── EventListener/
│       │           └── DomainEventToMessengerListener (bridge)
│       └── Persistence/
│           └── ReadModel/
│               ├── GiftCardReadModelProjection (10 handlers!)
│               ├── GiftCardReadModelRepository (Doctrine)
│               └── GiftCardReadModelQueryRepository
│
└── Interface/        ⭐ OpenAPI + validation
    └── Http/
        └── Controller/
            └── GiftCardController (11 endpointów, dobrze udokumentowanych)
```

**Verdict:** Struktura jest **wzorcowa**. Zero technical debt. Gotowa do rozbudowy.

---

## ✅ CO DZIAŁA ŚWIETNIE

### 1. Domain Layer (5/5)

#### GiftCard Aggregate
**Lokalizacja:** `src/Domain/GiftCard/Aggregate/GiftCard.php`

**Pozytywne:**
- ✅ Extends `Broadway\EventSourcedAggregateRoot` (correct)
- ✅ Wszystkie metody biznesowe zwracają `void` (event-driven)
- ✅ Apply methods dla każdego eventu
- ✅ Walidacja biznesowa w metodach publicznych
- ✅ Immutable state (tylko eventy go modyfikują)
- ✅ Rich domain model (8 metod biznesowych)
  - `create()`, `activate()`, `redeem()`, `suspend()`, `reactivate()`, `cancel()`, `expire()`, `adjustBalance()`, `decreaseBalance()`

**Metody biznesowe:**
```php
public static function create(...): self                      // Factory method
public function activate(...): void                           // INACTIVE → ACTIVE
public function redeem(Money $amount, ...): void              // Spend money
public function suspend(string $reason, ...): void            // ACTIVE → SUSPENDED
public function reactivate(...): void                         // SUSPENDED → ACTIVE (with date adjustment)
public function cancel(?string $reason, ...): void            // → CANCELLED
public function expire(...): void                             // → EXPIRED
public function adjustBalance(Money $adjustment, ...): void    // Admin correction (+ or -)
public function decreaseBalance(Money $amount, ...): void      // Admin correction (only -)
```

**Observations:**
- Metoda `suspend()` zapisuje `suspensionDurationSeconds` ✅ (to będzie użyte w `reactivate()`)
- Metoda `reactivate()` koryguje `expiresAt` dodając czas zawieszenia ✅ (smart!)
- Wszędzie sprawdzany status przed operacją ✅
- Balance sprawdzane przed redemption ✅
- Expiry date sprawdzana przy activate/redeem ✅

**Co BRAKUJE dla SaaS:**
- ❌ `cardNumber` (16-digit friendly ID)
- ❌ `activationCode` (12-digit code)
- ❌ `cardHolderId` (UUID Card Holdera)
- ❌ `cardHolderEmail` (email)
- ❌ `activatedByHolderAt` (timestamp)
- ❌ `markedAsStolenAt` (timestamp)
- ❌ Metoda `activateByHolder(cardHolderId, email)` - **oddzielna od `activate()`!**
- ❌ Metoda `markAsStolen(email, reason)`
- ❌ Status `STOLEN` w enum

---

#### Value Objects

**GiftCardId** (`src/Domain/GiftCard/ValueObject/GiftCardId.php`)
- ✅ Readonly properties
- ✅ UUID validation (ramsey/uuid)
- ✅ Factory methods (`generate()`, `fromString()`)
- ✅ Equality check
- ✅ toString() + __toString()

**Money** (`src/Domain/GiftCard/ValueObject/Money.php`)
- ✅ Readonly properties (amount int, currency string)
- ✅ Amount stored in **smallest unit** (grosze) - CORRECT!
- ✅ Currency validation (Symfony/Intl)
- ✅ No negative amounts
- ✅ Operations: `add()`, `subtract()`, `isGreaterThan()`, `equals()`, `isGreaterThanOrEqual()`
- ✅ Currency mismatch prevention

**Verdict:** Value Objects są **perfect**. Zero zmian potrzebnych.

---

#### Domain Events

**Zaimplementowane (10):**
1. `GiftCardCreated` - id, amount, currency, createdAt, expiresAt
2. `GiftCardActivated` - id, activatedAt
3. `GiftCardRedeemed` - id, amount, currency, redeemedAt
4. `GiftCardDepleted` - id, depletedAt
5. `GiftCardExpired` - id, expiredAt
6. `GiftCardSuspended` - id, reason, suspendedAt, suspensionDurationSeconds
7. `GiftCardReactivated` - id, reason, reactivatedAt, newExpiresAt
8. `GiftCardCancelled` - id, reason, cancelledAt
9. `GiftCardBalanceAdjusted` - id, adjustmentAmount, currency, reason, adjustedAt
10. `GiftCardBalanceDecreased` - id, amount, currency, reason, decreasedAt

**Observations:**
- ✅ Wszystkie eventy są **readonly**
- ✅ Primitive types only (string, int) - serializacja jest łatwa
- ✅ Immutable (brak setterów)
- ✅ All fields public (Broadway convention)

**Co BRAKUJE dla SaaS:**
- ❌ `GiftCardActivatedByHolder` - (cardHolderId, holderEmail, activatedAt)
- ❌ `GiftCardMarkedAsStolen` - (reportedBy email, reason, markedAt)
- ❌ `GiftCardHolderAssigned` - (cardHolderId, assignedBy admin, assignedAt) - opcjonalne

---

#### GiftCardStatus Enum

**Lokalizacja:** `src/Domain/GiftCard/Enum/GiftCardStatus.php`

**Obecne statusy:**
```php
case INACTIVE = 'inactive';
case ACTIVE = 'active';
case EXPIRED = 'expired';
case DEPLETED = 'depleted';
case CANCELLED = 'cancelled';
case SUSPENDED = 'suspended';
```

**Co BRAKUJE:**
- ❌ `case STOLEN = 'stolen';`

**Transition rules (obecne):**
- INACTIVE → ACTIVE (activate)
- ACTIVE → SUSPENDED (suspend)
- ACTIVE → EXPIRED (expire)
- ACTIVE → CANCELLED (cancel)
- ACTIVE → DEPLETED (redeem/adjustBalance → balance = 0)
- SUSPENDED → ACTIVE (reactivate)

**Nowe transitions dla SaaS:**
- INACTIVE → ACTIVE (activateByHolder) - **tylko Card Holder może to zrobić!**
- ACTIVE → STOLEN (markAsStolen)
- STOLEN → ACTIVE (unreportStolen - tylko admin)

---

#### Domain Exceptions

**Zaimplementowane (10):**
- `InvalidGiftCardIdException`
- `InvalidMoneyException`
- `InsufficientBalanceException`
- `GiftCardNotActiveException`
- `GiftCardNotFoundException`
- `WrongGiftCardStatusException`
- `InvalidExpirationDateException`
- `GiftCardNotExpiredException`
- `NoExpirationDateException`
- `InvalidSuspensionStateException`
- `GiftCardAlreadyExpiredException` ✅
- `GiftCardAlreadyActivatedException` ✅ (w listingu ale nie w kodzie?)

**Verdict:** Wyjątki są **very specific** i dobrze nazwane. ✅

---

### 2. Application Layer (5/5)

#### Commands (CQRS pattern)

**Zaimplementowane:**
- `CreateCommand` - amount, currency, expiresAt
- `RedeemCommand` - giftCardId, amount, currency
- `ActivateCommand` - id, activatedAt
- `SuspendCommand` - id, reason, suspendedAt, suspensionDurationSeconds
- `ReactivateCommand` - id, reason, reactivatedAt
- `CancelCommand` - id, reason, cancelledAt
- `ExpireCommand` - id, expiredAt
- `AdjustBalanceCommand` - id, amount, currency, reason, adjustedAt
- `DecreaseBalanceCommand` - id, amount, currency, reason, decreasedAt

**Observations:**
- ✅ **Readonly classes** (immutable)
- ✅ **Primitive types only** (int, string) - correct for CQRS!
- ✅ No domain objects in commands (zgodnie z best practices)
- ✅ Simple DTOs

**Co BRAKUJE dla SaaS:**
- ❌ `ActivateByCardHolderCommand` - (giftCardId, email, activationCode, privacyPolicyAccepted)
- ❌ `MarkAsStolenCommand` - (giftCardId, email, reason)

---

#### Command Handlers

**Pattern używany:** Provider/Persister (zamiast bezpośrednio Repository)

**Example:** `Create` handler
```php
public function __invoke(CreateCommand $command): string
{
    $giftCardId = GiftCardId::generate();

    $giftCard = GiftCard::create(
        $giftCardId,
        new Money($command->amount, $command->currency),
        null,
        $command->expiresAt ? new DateTimeImmutable($command->expiresAt) : null
    );

    $this->persister->handle($giftCard); // saves to repository

    return $giftCardId->toString(); // returns ID
}
```

**Observations:**
- ✅ `Create` handler **zwraca ID** (wyjątek od reguły "void")
- ✅ Inne handlery zwracają `void` (correct)
- ✅ Provider pattern ładuje agregaty
- ✅ Persister pattern zapisuje agregaty
- ✅ Wstrzykiwanie dependencies przez constructor

**Verdict:** Handlery są **clean i zgodne z CQRS**. Zero technical debt.

---

#### Provider & Persister Pattern

**GiftCardProvider:**
```php
public function loadFromId(GiftCardId $id): GiftCard
{
    $giftCard = $this->repository->load($id);
    if ($giftCard === null) {
        throw GiftCardNotFoundException::forId($id);
    }
    return $giftCard;
}
```

**GiftCardPersister:**
```php
public function handle(GiftCard $giftCard): void
{
    $this->repository->save($giftCard);
}
```

**Observations:**
- ✅ Provider **rzuca wyjątek** jeśli nie znaleziono (correct)
- ✅ Persister jest **simple wrapper** (separacja concerns)
- ✅ Port/Adapter pattern (Application nie zna Infrastructure)

**Verdict:** Pattern **perfectly implemented**. To wzór do naśladowania.

---

#### Read Model (CQRS Query Side)

**GiftCardReadModel** (`src/Application/GiftCard/ReadModel/GiftCardReadModel.php`)

**Observations:**
- ✅ Denormalized data (balanceAmount, status, all dates)
- ✅ `updateFromEvent()` method (aktualizuje `updated_at`)
- ✅ Separate Read/Write repositories
- ✅ Query side zwraca **Views** (nie domain objects)

**GiftCardReadModelProjection:**
- ✅ **10 event handlers** (po jednym na każdy domain event!)
- ✅ Każdy handler jako `#[AsMessageHandler]` (Symfony Messenger)
- ✅ Idempotent (sprawdza czy readModel istnieje)
- ✅ Async (eventy z `async_events` transport)

**Example:**
```php
#[AsMessageHandler]
public function onGiftCardCreated(GiftCardCreated $event): void
{
    $readModel = new GiftCardReadModel(
        id: $event->id,
        balanceAmount: $event->amount,
        balanceCurrency: $event->currency,
        initialAmount: $event->amount,
        initialCurrency: $event->currency,
        status: 'INACTIVE',
        createdAt: new \DateTimeImmutable($event->createdAt),
        expiresAt: $event->expiresAt ? new \DateTimeImmutable($event->expiresAt) : null
    );

    $this->repository->save($readModel);
}
```

**Verdict:** Read Model projection jest **asynchroniczna i eventual consistent**. Perfect for CQRS! ⭐

**Co BRAKUJE:**
- ❌ tenant_id w GiftCardReadModel
- ❌ cardNumber w GiftCardReadModel
- ❌ cardHolderEmail w GiftCardReadModel
- ❌ Handlery dla nowych eventów (GiftCardActivatedByHolder, GiftCardMarkedAsStolen)

---

### 3. Infrastructure Layer (5/5)

#### Broadway Event Store Integration

**GiftCardRepositoryBroadway** (`src/Infrastructure/GiftCard/EventSourcing/Broadway/GiftCardRepositoryBroadway.php`)

```php
public function load(GiftCardId $id): ?GiftCard
{
    try {
        $giftCard = $this->inner->load($id->toString());
        return $giftCard;
    } catch (AggregateNotFoundException) {
        return null;
    }
}

public function save(GiftCard $giftCard): void
{
    $this->inner->save($giftCard);
}
```

**Observations:**
- ✅ **Adapter pattern** (wraps Broadway repository)
- ✅ Converts domain `GiftCardId` to string (Broadway requires string)
- ✅ Returns `null` instead of throwing (domain-friendly)
- ✅ Clean separation (Infrastructure adapts to Domain, not vice versa)

**Verdict:** Adapter jest **minimal i correct**. ✅

**Co TRZEBA ZMIENIĆ dla SaaS:**
- 🔄 Wrap this with **TenantAwareEventStore** (decorator)
- 🔄 Inject tenant_id into event metadata on save()
- 🔄 Filter events by tenant_id on load()

---

#### Domain Events → Messenger Bridge

**DomainEventToMessengerListener** (`src/Infrastructure/GiftCard/EventSourcing/Broadway/EventListener/DomainEventToMessengerListener.php`)

```php
public function handle(DomainMessage $domainMessage): void
{
    // Extract the domain event (payload) from Broadway's DomainMessage wrapper
    $event = $domainMessage->getPayload();

    // Dispatch to Symfony Messenger
    $this->messageBus->dispatch($event);
}
```

**Flow:**
1. Aggregate emits event → Repository saves → EventStore persists
2. EventStore publishes to EventBus
3. EventBus calls this listener
4. Listener dispatches to Symfony Messenger
5. Messenger routes to `async_events` transport (RabbitMQ fanout)
6. Workers process asynchronously

**Verdict:** Bridge jest **perfectly implemented**. Eventy trafiają do RabbitMQ automatycznie. ⭐

---

#### Configuration

**services.yaml** (`config/services.yaml`)

**Broadway setup:**
```yaml
# Event Store
Broadway\EventStore\EventStore:
    class: Broadway\EventStore\Dbal\DBALEventStore
    arguments:
        - '@doctrine.dbal.default_connection'
        - '@broadway.serializer.reflection'  # ⭐ ReflectionSerializer
        - '@broadway.serializer.reflection'
        - 'events'
        - false

# ReflectionSerializer - handles Value Objects automatically!
broadway.serializer.reflection:
    class: Broadway\Serializer\ReflectionSerializer
```

**Observations:**
- ✅ `ReflectionSerializer` automatycznie serializuje Value Objects (GiftCardId, Money) ⭐
- ✅ Nie trzeba implementować `Serializable` interface
- ✅ Table name: `events`

**Event Sourcing Repository:**
```yaml
broadway.repository.gift_card:
    class: Broadway\EventSourcing\EventSourcingRepository
    arguments:
        - '@Broadway\EventStore\EventStore'
        - '@Broadway\EventHandling\EventBus'
        - 'App\Domain\GiftCard\Aggregate\GiftCard'
        - '@Broadway\EventSourcing\AggregateFactory\AggregateFactory'
```

**Observations:**
- ✅ EventBus wstrzyknięty (emituje eventy po save)
- ✅ AggregateFactory używa `PublicConstructorAggregateFactory` (standard)

**EventBus setup:**
```yaml
Broadway\EventHandling\EventBus:
    class: Broadway\EventHandling\SimpleEventBus
    calls:
        - ['subscribe', ['@App\Infrastructure\GiftCard\EventSourcing\Broadway\EventListener\DomainEventToMessengerListener']]
```

**Observations:**
- ✅ EventBus subskrybuje nasz listener który przekazuje do Messenger

**Port bindings:**
```yaml
App\Domain\GiftCard\Port\GiftCardRepository: '@App\Infrastructure\GiftCard\EventSourcing\Broadway\GiftCardRepositoryBroadway'
App\Application\GiftCard\Port\GiftCardProviderInterface: '@App\Application\GiftCard\Provider\GiftCardProvider'
App\Application\GiftCard\Port\GiftCardPersisterInterface: '@App\Application\GiftCard\Persister\GiftCardPersister'
```

**Verdict:** Configuration jest **perfect**. Wszystkie zależności dobrze wstrzykowane.

---

**messenger.yaml** (`config/packages/messenger.yaml`)

**Transports:**
```yaml
async:  # Commands → RabbitMQ (direct exchange)
    dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
    options:
        exchange:
            name: 'gift_card_commands'
            type: direct
        queues:
            gift_card_commands: ~

async_events:  # Domain Events → RabbitMQ (fanout exchange)
    dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
    options:
        exchange:
            name: 'gift_card_events'
            type: fanout
        queues:
            gift_card_events: ~

failed: 'doctrine://default?queue_name=failed'
```

**Observations:**
- ✅ **Fanout exchange** dla eventów (multiple consumers możliwe) ⭐
- ✅ **Direct exchange** dla komend (single consumer)
- ✅ Failed messages w Doctrine (debugging friendly)
- ✅ Retry strategy: 3 retries, exponential backoff

**Routing:**
```yaml
routing:
    # Commands
    'App\Application\GiftCard\Command\RedeemCommand': async
    'App\Application\GiftCard\Command\ActivateCommand': async
    # ... (8 commands routed to async)

    # Domain Events
    'App\Domain\GiftCard\Event\GiftCardCreated': async_events
    'App\Domain\GiftCard\Event\GiftCardRedeemed': async_events
    # ... (10 events routed to async_events)
```

**Observations:**
- ✅ `CreateCommand` **SYNC** (nie ma routingu) - zwraca ID natychmiast
- ✅ Pozostałe komendy **ASYNC** (HTTP 202 Accepted)
- ✅ Wszystkie eventy **ASYNC** (Read Model eventual consistent)

**Verdict:** Messenger configuration jest **production-ready**. ⭐

---

### 4. Interface Layer (HTTP API)

**GiftCardController** (`src/Interface/Http/Controller/GiftCardController.php`)

**Endpointy (11):**
1. `POST /api/gift-cards/create` - Create
2. `POST /api/gift-cards/{id}/redeem` - Redeem
3. `POST /api/gift-cards/{id}/activate` - Activate
4. `POST /api/gift-cards/{id}/suspend` - Suspend
5. `POST /api/gift-cards/{id}/reactivate` - Reactivate
6. `POST /api/gift-cards/{id}/cancel` - Cancel
7. `POST /api/gift-cards/{id}/expire` - Expire
8. `POST /api/gift-cards/{id}/adjust-balance` - Adjust (admin)
9. `POST /api/gift-cards/{id}/decrease-balance` - Decrease (admin)
10. `GET /api/gift-cards/{id}` - Get (Query)
11. `GET /api/gift-cards/{id}/history` - History (Query)
12. `GET /api/gift-cards/health` - Health check

**Observations:**
- ✅ OpenAPI annotations (Swagger docs automatyczne) ⭐
- ✅ Validation przez Symfony Validator
- ✅ JSON request/response
- ✅ UUID validation
- ✅ Error handling (`handleDomainException`)
- ✅ HTTP status codes:
  - 201 Created (create)
  - 202 Accepted (async commands)
  - 200 OK (queries)
  - 404 Not Found (queries)
  - 400 Bad Request (validation)
  - 500 Internal Error

**Example response (create):**
```json
{
  "message": "Gift card created successfully",
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "created"
}
```

**Example response (async command):**
```json
{
  "message": "Gift card redeem command dispatched",
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "pending"
}
```

**Verdict:** API jest **RESTful i dobrze udokumentowane**. OpenAPI docs to ⭐.

**Co TRZEBA ZMIENIĆ:**
- 🔄 Wszystkie endpointy (oprócz `/health`) wymagają **HMAC authentication**
- 🔄 Dodać prefix `/v1/` (versioning)
- 🔄 Zwracać `activation_code` i `card_number` w create response
- 🔄 Dodać `X-Tenant-ID` header w responses
- 🔄 Dodać rate limiting headers

---

### 5. Tests

**Lokalizacja:** `tests/GiftCard/`

**Struktura:**
```
tests/GiftCard/
├── Domain/
│   └── Aggregate/
│       └── GiftCardTest.php
├── Application/
│   └── Handler/
│       ├── CreateTest.php
│       ├── RedeemTest.php
│       ├── ActivateTest.php
│       └── ... (9 handler tests)
├── Infrastructure/
│   ├── Persistence/
│   │   └── ReadModel/
│   │       └── GiftCardReadModelProjectionTest.php
│   └── Integration/
│       └── EventStore/
│           └── GiftCardEventStoreTest.php
```

**Observations:**
- ✅ Unit tests dla Aggregate (domain logic)
- ✅ Integration tests dla Handlers
- ✅ Integration tests dla Read Model Projection
- ✅ Integration tests dla Event Store

**Verdict:** Test coverage wygląda **solid**. ✅

---

## ❌ CO BRAKUJE DLA SAAS (Migration Checklist)

### Phase 1: Multi-Tenant Foundation

**1.1. Domain Layer - Tenant Bounded Context**

**Nowe pliki do utworzenia:**
```
src/Domain/Tenant/
├── Aggregate/
│   └── Tenant.php                      # Aggregate root
├── ValueObject/
│   ├── TenantId.php                    # UUID
│   ├── TenantName.php                  # Name with validation
│   └── TenantStatus.php                # ACTIVE/SUSPENDED
├── Event/
│   ├── TenantCreated.php
│   ├── TenantSuspended.php
│   ├── TenantReactivated.php
│   └── ApiCredentialRotated.php
├── Exception/
│   ├── TenantNotFoundException.php
│   └── TenantSuspendedException.php
└── Port/
    └── TenantRepository.php
```

**Tenant Aggregate (pseudo-code):**
```php
class Tenant extends EventSourcedAggregateRoot
{
    private TenantId $id;
    private TenantName $name;
    private TenantStatus $status;
    private Email $contactEmail;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $suspendedAt;

    public static function create(TenantId $id, TenantName $name, Email $contactEmail): self;
    public function suspend(string $reason): void;
    public function reactivate(): void;

    // Events applied internally
}
```

---

**1.2. Domain Layer - GiftCard Extensions**

**Modyfikacje w `GiftCard.php`:**

```php
class GiftCard extends EventSourcedAggregateRoot
{
    // Existing fields...

    // ⭐ NEW FIELDS for Consumer Portal:
    private ?CardNumber $cardNumber = null;              // 16 digits
    private ?string $activationCode = null;              // 12 digits
    private ?CardHolderId $cardHolderId = null;          // UUID
    private ?string $cardHolderEmail = null;             // Email
    private ?DateTimeImmutable $activatedByHolderAt = null;
    private ?DateTimeImmutable $markedAsStolenAt = null;

    // ⭐ NEW METHODS:
    public function activateByHolder(
        CardHolderId $cardHolderId,
        string $email
    ): void {
        if ($this->status !== GiftCardStatus::INACTIVE) {
            throw WrongGiftCardStatusException::create(GiftCardStatus::INACTIVE, $this->status);
        }

        $this->apply(new GiftCardActivatedByHolder(
            $this->id->toString(),
            $cardHolderId->toString(),
            $email,
            (new DateTimeImmutable())->format('Y-m-d\TH:i:s.uP')
        ));
    }

    public function markAsStolen(string $reportedBy, ?string $reason = null): void {
        if ($this->status !== GiftCardStatus::ACTIVE) {
            throw WrongGiftCardStatusException::create(GiftCardStatus::ACTIVE, $this->status);
        }

        $this->apply(new GiftCardMarkedAsStolen(
            $this->id->toString(),
            $reportedBy,
            $reason,
            (new DateTimeImmutable())->format('Y-m-d\TH:i:s.uP')
        ));
    }

    protected function applyGiftCardActivatedByHolder(GiftCardActivatedByHolder $event): void {
        $this->status = GiftCardStatus::ACTIVE;
        $this->cardHolderId = CardHolderId::fromString($event->cardHolderId);
        $this->cardHolderEmail = $event->holderEmail;
        $this->activatedByHolderAt = new DateTimeImmutable($event->activatedAt);
    }

    protected function applyGiftCardMarkedAsStolen(GiftCardMarkedAsStolen $event): void {
        $this->status = GiftCardStatus::STOLEN;
        $this->markedAsStolenAt = new DateTimeImmutable($event->markedAt);
    }
}
```

**Modyfikacje w `GiftCard::create()`:**
```php
public static function create(
    GiftCardId $id,
    Money $amount,
    ?DateTimeImmutable $createdAt = null,
    ?DateTimeImmutable $expiresAt = null
): self
{
    // ... existing code ...

    $giftCard->apply(new GiftCardCreated(
        $id->toString(),
        $amount->getAmount(),
        $amount->getCurrency(),
        $finalCreatedAt->format('Y-m-d\TH:i:s.uP'),
        $finalExpiresAt->format('Y-m-d\TH:i:s.uP'),
        CardNumber::generate()->toString(),        // ⭐ NEW
        ActivationCodeGenerator::generate()         // ⭐ NEW (12 digits)
    ));

    return $giftCard;
}
```

---

**1.3. New Value Objects**

```
src/Domain/GiftCard/ValueObject/
├── CardNumber.php        # 16 digits, Luhn algorithm validation
├── ActivationCode.php    # 12 digits, cryptographically random

src/Domain/CardHolder/ValueObject/
├── CardHolderId.php      # UUID
└── Email.php             # Email with validation
```

**CardNumber:**
```php
final class CardNumber
{
    private readonly string $value; // 16 digits

    private function __construct(string $value) {
        if (!preg_match('/^\d{16}$/', $value)) {
            throw new InvalidCardNumberException();
        }

        // Optional: Luhn algorithm validation
        if (!$this->validateLuhn($value)) {
            throw new InvalidCardNumberException();
        }

        $this->value = $value;
    }

    public static function generate(): self {
        // Generate random 16-digit number with valid Luhn checksum
        $number = self::generateWithLuhn();
        return new self($number);
    }

    public static function fromString(string $value): self {
        return new self($value);
    }

    public function toString(): string {
        return $this->value;
    }

    public function toFormatted(): string {
        // 1234-5678-9012-3456
        return substr($this->value, 0, 4) . '-' .
               substr($this->value, 4, 4) . '-' .
               substr($this->value, 8, 4) . '-' .
               substr($this->value, 12, 4);
    }

    public function toMasked(): string {
        // ****-****-****-3456
        return '****-****-****-' . substr($this->value, 12, 4);
    }
}
```

---

**1.4. New Domain Events**

```
src/Domain/GiftCard/Event/
├── GiftCardActivatedByHolder.php
└── GiftCardMarkedAsStolen.php
```

```php
final readonly class GiftCardActivatedByHolder
{
    public function __construct(
        public string $id,              // GiftCard UUID
        public string $cardHolderId,    // CardHolder UUID
        public string $holderEmail,
        public string $activatedAt,
    ) {}
}

final readonly class GiftCardMarkedAsStolen
{
    public function __construct(
        public string $id,
        public string $reportedBy,      // Email
        public ?string $reason,
        public string $markedAt,
    ) {}
}
```

---

**1.5. GiftCardStatus Enum - Add STOLEN**

```php
// src/Domain/GiftCard/Enum/GiftCardStatus.php

enum GiftCardStatus: string
{
    case INACTIVE = 'inactive';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case DEPLETED = 'depleted';
    case CANCELLED = 'cancelled';
    case SUSPENDED = 'suspended';
    case STOLEN = 'stolen';          // ⭐ NEW
}
```

---

**1.6. Infrastructure - TenantAwareEventStore**

**Nowy plik:** `src/Infrastructure/GiftCard/EventSourcing/Broadway/TenantAwareEventStore.php`

```php
namespace App\Infrastructure\GiftCard\EventSourcing\Broadway;

use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\EventStore\EventStore;
use App\Infrastructure\Tenant\Context\TenantContext;
use App\Domain\Tenant\Exception\TenantMismatchException;

final class TenantAwareEventStore implements EventStore
{
    public function __construct(
        private EventStore $innerEventStore,    // Broadway's DBALEventStore
        private TenantContext $tenantContext,
    ) {}

    public function load($id): DomainEventStream
    {
        $tenantId = $this->tenantContext->getTenantId();
        $stream = $this->innerEventStore->load($id);

        // SECURITY: Verify all events belong to current tenant
        $events = [];
        foreach ($stream as $domainMessage) {
            $metadata = $domainMessage->getMetadata();
            $eventTenantId = $metadata['tenant_id'] ?? null;

            if ($eventTenantId !== $tenantId->toString()) {
                throw new TenantMismatchException(
                    sprintf('Tenant %s attempted to load aggregate %s belonging to tenant %s',
                        $tenantId, $id, $eventTenantId)
                );
            }

            $events[] = $domainMessage;
        }

        return new DomainEventStream($events);
    }

    public function append($id, DomainEventStream $eventStream): void
    {
        $tenantId = $this->tenantContext->getTenantId();

        // Inject tenant_id into metadata
        $enrichedEvents = [];
        foreach ($eventStream as $domainMessage) {
            $metadata = $domainMessage->getMetadata();
            $metadata['tenant_id'] = $tenantId->toString();

            $enrichedEvents[] = $domainMessage->andMetadata($metadata);
        }

        $this->innerEventStore->append($id, new DomainEventStream($enrichedEvents));
    }

    public function loadFromPlayhead($id, int $playhead): DomainEventStream
    {
        // Similar to load() but with playhead
        // Implementation omitted for brevity
    }
}
```

**Configuration update:**
```yaml
# config/services.yaml

# Inner event store (Broadway)
broadway.event_store.inner:
    class: Broadway\EventStore\Dbal\DBALEventStore
    arguments:
        - '@doctrine.dbal.default_connection'
        - '@broadway.serializer.reflection'
        - '@broadway.serializer.reflection'
        - 'events'
        - false

# Tenant-aware wrapper ⭐ NEW
App\Infrastructure\GiftCard\EventSourcing\Broadway\TenantAwareEventStore:
    arguments:
        - '@broadway.event_store.inner'
        - '@App\Infrastructure\Tenant\Context\TenantContext'

# Bind to interface
Broadway\EventStore\EventStore: '@App\Infrastructure\GiftCard\EventSourcing\Broadway\TenantAwareEventStore'
```

---

**1.7. TenantContext Service**

**Nowy plik:** `src/Infrastructure/Tenant/Context/TenantContext.php`

```php
namespace App\Infrastructure\Tenant\Context;

use App\Domain\Tenant\ValueObject\TenantId;

class TenantContext
{
    private ?TenantId $tenantId = null;
    private bool $adminBypass = false;

    public function setTenantId(TenantId $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): TenantId
    {
        if ($this->tenantId === null) {
            throw new TenantContextNotSetException(
                'TenantContext not initialized. This is a security error!'
            );
        }
        return $this->tenantId;
    }

    public function hasTenantId(): bool
    {
        return $this->tenantId !== null;
    }

    public function enableAdminBypass(): void
    {
        $this->adminBypass = true;
    }

    public function isAdminBypass(): bool
    {
        return $this->adminBypass;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->adminBypass = false;
    }
}
```

**Configuration:**
```yaml
# config/services.yaml
services:
    App\Infrastructure\Tenant\Context\TenantContext:
        scope: request  # Fresh instance per HTTP request
```

---

**1.8. Database Migrations**

**Migration 1:** Add tenant_id to events table
```sql
-- migrations/003_add_tenant_id_to_events.sql

ALTER TABLE events
ADD COLUMN tenant_id UUID;

-- Backfill for existing data (if any)
-- UPDATE events SET tenant_id = '<default-tenant-uuid>' WHERE tenant_id IS NULL;

ALTER TABLE events
ALTER COLUMN tenant_id SET NOT NULL;

-- Add indexes
CREATE INDEX idx_events_tenant_aggregate ON events(tenant_id, aggregate_uuid, playhead);
CREATE INDEX idx_events_tenant_id ON events(tenant_id);
```

**Migration 2:** Add tenant_id to gift_cards_read
```sql
-- migrations/004_add_tenant_id_to_read_model.sql

ALTER TABLE gift_cards_read
ADD COLUMN tenant_id UUID NOT NULL;

CREATE INDEX idx_gift_cards_read_tenant ON gift_cards_read(tenant_id);
```

**Migration 3:** PostgreSQL RLS
```sql
-- migrations/005_enable_rls.sql

-- Enable RLS
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE gift_cards_read ENABLE ROW LEVEL SECURITY;

-- Create policies
CREATE POLICY tenant_isolation_policy ON events
FOR ALL
TO PUBLIC
USING (
    tenant_id = current_setting('app.tenant_id', true)::uuid
    OR current_setting('app.admin_bypass', true) = 'true'
);

CREATE POLICY tenant_isolation_policy ON gift_cards_read
FOR ALL
TO PUBLIC
USING (
    tenant_id = current_setting('app.tenant_id', true)::uuid
    OR current_setting('app.admin_bypass', true) = 'true'
);
```

---

### Phase 2: HMAC Authentication

**Files to create:**
```
src/Infrastructure/Tenant/Http/Middleware/
└── HmacAuthenticationMiddleware.php

src/Infrastructure/Tenant/Security/
├── HmacValidator.php
└── NonceValidator.php

src/Infrastructure/Tenant/Persistence/
└── TenantApiCredentialRepository.php

src/Domain/Tenant/ValueObject/
├── ApiKey.php
└── ApiSecret.php (encrypted storage)
```

**Implementation details in:** `docs/hmac-auth.md` (already created)

---

### Phase 3: Consumer Portal (CardHolder)

**Files to create:**
```
src/Domain/CardHolder/
├── Entity/
│   ├── CardHolder.php
│   ├── MagicLink.php
│   └── CardHolderSession.php
├── ValueObject/
│   ├── CardHolderId.php
│   └── Email.php
└── Port/
    └── CardHolderRepository.php

src/Application/CardHolder/
├── Command/
│   ├── ActivateCardCommand.php
│   ├── RequestMagicLinkCommand.php
│   ├── MarkCardAsStolenCommand.php
│   └── AnonymizeCardHolderCommand.php
└── Handler/
    ├── ActivateCard.php
    ├── RequestMagicLink.php
    ├── MarkCardAsStolen.php
    └── AnonymizeCardHolder.php

src/Interface/Http/Controller/
└── CardHolderPortalController.php
```

**Implementation details in:** `docs/consumer-flow.md` (already created)

---

### Phase 4: Email Notifications

**Files to create:**
```
src/Application/Email/
├── Command/
│   └── SendEmailCommand.php
└── Handler/
    └── SendEmail.php

src/Infrastructure/Email/
├── EventSubscriber/
│   ├── CardActivationEmailSubscriber.php
│   ├── LowBalanceWarningSubscriber.php
│   └── CardExpiringSubscriber.php
└── Service/
    └── SymfonyMailerAdapter.php

templates/email/
├── card-activation-confirmation.html.twig
├── magic-link.html.twig
├── low-balance-warning.html.twig
└── card-expiring-soon.html.twig
```

---

### Phase 5: Admin Panel

**Files to create:**
```
src/Domain/Admin/
├── Entity/
│   ├── AdminUser.php
│   └── AdminUserOtp.php
└── ValueObject/
    ├── Email.php
    ├── HashedPassword.php
    └── OtpCode.php

src/Interface/Admin/Controller/
├── AdminAuthController.php
├── TenantManagementController.php
├── GiftCardManagementController.php
└── CardHolderManagementController.php
```

---

## 📊 Complexity Estimate

**Lines of Code to Add:**
- Phase 1 (Multi-tenant): ~2,000 LOC
- Phase 2 (HMAC): ~800 LOC
- Phase 3 (Consumer Portal): ~1,500 LOC
- Phase 4 (Email): ~500 LOC
- Phase 5 (Admin Panel): ~2,000 LOC
- Phase 6 (GDPR): ~600 LOC
- Phase 7 (Webhooks): ~1,000 LOC
- Phase 8 (Production): ~500 LOC

**Total:** ~9,000 LOC (+ tests)

**Estimated Time (full-time work):**
- Phase 1: 2 weeks
- Phase 2: 2 weeks
- Phase 3: 3 weeks
- Phase 4: 1 week
- Phase 5: 3 weeks
- Phase 6: 2 weeks
- Phase 7: 2 weeks
- Phase 8: 2 weeks

**Total: ~17 weeks (~4 months)**

---

## ⚠️ Critical Warnings

### 1. Event Store Tenant Isolation is SECURITY-CRITICAL

**Problem:** Jeśli źle zaimplementujesz `TenantAwareEventStore`, Tenant A może odtworzyć agregat Tenant B!

**Solution:**
- ✅ ZAWSZE sprawdzaj `tenant_id` w metadanych eventów podczas `load()`
- ✅ ZAWSZE wstrzykuj `tenant_id` podczas `append()`
- ✅ Dodaj RLS policies jako **defense in depth**
- ✅ Testuj cross-tenant isolation w integration tests

### 2. RLS Requires Doctrine Middleware

**Problem:** Doctrine nie ustawia session variables automatycznie.

**Solution:** Użyj Doctrine DBAL Middleware (jak w `docs/tenant-isolation.md`)

### 3. HMAC Secret Storage

**Problem:** Nie możesz hashować secretu (bcrypt), bo potrzebujesz go do HMAC verification.

**Solution:** Encrypt secret z `defuse/php-encryption` (jak w `docs/hmac-auth.md`)

### 4. CardHolder vs Tenant Scope

**Problem:** CardHolder może mieć karty od wielu Tenantów.

**Solution:**
- CardHolder, MagicLink, CardHolderSession są **CROSS-TENANT** (bez RLS)
- GiftCard ma `tenant_id` + `cardHolderId` (both!)

### 5. Event Schema Evolution

**Problem:** Dodajesz nowe pola do eventów (cardNumber, activationCode).

**Solution:**
- Nowe eventy: `GiftCardActivatedByHolder`, `GiftCardMarkedAsStolen`
- Stare eventy (`GiftCardCreated`) rozszerzasz, ale musisz zachować **backward compatibility**
- Używaj nullable fields w starych eventach

---

## ✅ Summary & Recommendations

### Co zrobiono ŚWIETNIE:

1. ⭐ **Clean Architecture** - separacja warstw idealna
2. ⭐ **Event Sourcing** - Broadway dobrze zintegrowany
3. ⭐ **CQRS** - Command/Query separation poprawna
4. ⭐ **Async messaging** - RabbitMQ + Messenger
5. ⭐ **Read Model** - eventual consistency zaimplementowana
6. ⭐ **Value Objects** - immutable i validated
7. ⭐ **OpenAPI** - dokumentacja automatyczna
8. ⭐ **Tests** - dobry coverage

### Co dodać:

1. 🔨 **Multi-tenancy** - TenantAwareEventStore + RLS
2. 🔨 **HMAC auth** - dla Tenant API
3. 🔨 **Consumer Portal** - CardHolder context
4. 🔨 **Admin Panel** - zarządzanie systemem
5. 🔨 **Emails** - notifications
6. 🔨 **Webhooks** - integracja z Tenant systems
7. 🔨 **GDPR** - anonymization
8. 🔨 **Production** - rate limiting, monitoring

### Recommended Order:

1. **Start with Phase 1** (Multi-tenant foundation) - to jest fundament
2. **Then Phase 2** (HMAC) - zabezpiecz API
3. **Then Phase 3** (Consumer Portal) - core functionality dla B2C
4. Pozostałe fazy według potrzeb

---

## 📝 Next Steps

**Pytanie do Ciebie:**

Czy chcesz żebym:
1. **Zaczął implementację Phase 1** (Multi-tenant foundation)?
2. **Stworzył szczegółowe diagramy architektury** (Mermaid/PlantUML)?
3. **Przygotował migration scripts** (SQL + PHP)?
4. **Zrobił coś innego?**

**Jestem gotowy do startu!** 🚀
