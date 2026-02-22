# Tenant Isolation & Row-Level Security (RLS)

## Overview

This document describes the multi-tenant isolation strategy using PostgreSQL Row-Level Security (RLS) and Event Sourcing tenant awareness.

**Isolation Goal:** 🔒 Tenant A cannot access Tenant B's data, **even if application code has bugs**.

**Strategy:**
- Application layer: TenantContext + TenantAwareEventStore
- Database layer: RLS policies (defense in depth)

---

## Architecture Layers

```
┌─────────────────────────────────────────────────────────────────┐
│ Layer 1: HTTP Middleware (HMAC Auth)                           │
│ - Validates tenant API key                                      │
│ - Sets TenantContext with tenant_id                            │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ Layer 2: TenantContext (Request Scope)                          │
│ - Stores current tenant_id for this request                    │
│ - Throws exception if not set when needed                      │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ Layer 3: TenantAwareEventStore (Application)                   │
│ - Injects tenant_id when saving events                         │
│ - Filters events by tenant_id when loading                     │
│ - Security check: aggregate tenant_id must match context       │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ Layer 4: Doctrine DBAL Middleware                              │
│ - Sets PostgreSQL session variable on connect                  │
│ - Executes: SET LOCAL app.tenant_id = '<uuid>'                 │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ Layer 5: PostgreSQL RLS (Database)                             │
│ - Enforces WHERE tenant_id = current_setting('app.tenant_id')  │
│ - Blocks queries that violate policy                           │
│ - Defense in depth (even if app layer fails)                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Component 1: TenantContext Service

**Purpose:** Store current tenant_id in request scope

**Implementation:**

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
                'TenantContext not initialized. This is a critical security error!'
            );
        }

        return $this->tenantId;
    }

    public function hasTenantId(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Allow admin to bypass RLS and access all tenants.
     * Use with EXTREME CAUTION!
     */
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

**Service registration:**

```yaml
# config/services.yaml

services:
    App\Infrastructure\Tenant\Context\TenantContext:
        # Request-scoped: fresh instance per HTTP request
        scope: request
```

**Usage in middleware:**

```php
// After HMAC validation
$tenant = $this->tenantRepository->findByApiKey($apiKey);
$this->tenantContext->setTenantId($tenant->getId());
```

---

## Component 2: TenantAwareEventStore

**Purpose:** Wrap Broadway's DBALEventStore to inject/filter tenant_id

**Critical Security Property:**
- Tenant A cannot load aggregate created by Tenant B
- Tenant A cannot save events with Tenant B's tenant_id

**Implementation:**

```php
namespace App\Infrastructure\GiftCard\EventSourcing\Broadway;

use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\EventStore\EventStore;
use Broadway\EventStore\Exception\DuplicatePlayheadException;
use App\Infrastructure\Tenant\Context\TenantContext;
use App\Domain\Tenant\Exception\TenantMismatchException;

class TenantAwareEventStore implements EventStore
{
    public function __construct(
        private EventStore $innerEventStore, // Broadway's DBALEventStore
        private TenantContext $tenantContext,
    ) {}

    public function load($id): DomainEventStream
    {
        // Get current tenant from context
        $tenantId = $this->tenantContext->getTenantId();

        // Load events from inner store
        $stream = $this->innerEventStore->load($id);

        // SECURITY CHECK: Verify all events belong to current tenant
        $events = [];
        foreach ($stream as $domainMessage) {
            $metadata = $domainMessage->getMetadata();

            if (!isset($metadata['tenant_id'])) {
                throw new \RuntimeException(
                    sprintf('Event %s missing tenant_id metadata', $domainMessage->getId())
                );
            }

            $eventTenantId = $metadata['tenant_id'];

            if ($eventTenantId !== $tenantId->toString()) {
                // CRITICAL: Tenant trying to load another tenant's aggregate!
                throw new TenantMismatchException(
                    sprintf(
                        'Security violation: Tenant %s attempted to load aggregate %s belonging to tenant %s',
                        $tenantId->toString(),
                        $id,
                        $eventTenantId
                    )
                );
            }

            $events[] = $domainMessage;
        }

        return new DomainEventStream($events);
    }

    public function loadFromPlayhead($id, int $playhead): DomainEventStream
    {
        $tenantId = $this->tenantContext->getTenantId();

        $stream = $this->innerEventStore->loadFromPlayhead($id, $playhead);

        // Same security check as load()
        $events = [];
        foreach ($stream as $domainMessage) {
            $metadata = $domainMessage->getMetadata();
            $eventTenantId = $metadata['tenant_id'] ?? null;

            if ($eventTenantId !== $tenantId->toString()) {
                throw new TenantMismatchException(/* ... */);
            }

            $events[] = $domainMessage;
        }

        return new DomainEventStream($events);
    }

    public function append($id, DomainEventStream $eventStream): void
    {
        // Get current tenant from context
        $tenantId = $this->tenantContext->getTenantId();

        // Enrich events with tenant_id metadata
        $enrichedEvents = [];
        foreach ($eventStream as $domainMessage) {
            $metadata = $domainMessage->getMetadata();

            // Check if tenant_id already set (should not be!)
            if (isset($metadata['tenant_id'])) {
                $existingTenantId = $metadata['tenant_id'];

                if ($existingTenantId !== $tenantId->toString()) {
                    // CRITICAL: Trying to save events for another tenant!
                    throw new TenantMismatchException(
                        sprintf(
                            'Security violation: Tenant %s attempted to save events for tenant %s',
                            $tenantId->toString(),
                            $existingTenantId
                        )
                    );
                }
            }

            // Inject tenant_id into metadata
            $metadata['tenant_id'] = $tenantId->toString();

            $enrichedEvents[] = $domainMessage->andMetadata($metadata);
        }

        // Save to inner store
        $this->innerEventStore->append($id, new DomainEventStream($enrichedEvents));
    }
}
```

**Service registration:**

```yaml
# config/services.yaml

services:
    # Inner event store (Broadway)
    broadway.event_store.dbal:
        class: Broadway\EventStore\DBALEventStore
        arguments:
            - '@doctrine.dbal.default_connection'
            - '@broadway.serializer.payload'
            - '@broadway.serializer.metadata'
            - 'events'
            - true # use binary

    # Tenant-aware wrapper
    App\Infrastructure\GiftCard\EventSourcing\Broadway\TenantAwareEventStore:
        decorates: broadway.event_store.dbal
        arguments:
            - '@.inner' # Inner DBALEventStore
            - '@App\Infrastructure\Tenant\Context\TenantContext'

    # Alias for easy access
    Broadway\EventStore\EventStore: '@App\Infrastructure\GiftCard\EventSourcing\Broadway\TenantAwareEventStore'
```

---

## Component 3: Database Schema Changes

### Migration: Add tenant_id to events table

```sql
-- migrations/003_add_tenant_id_to_events.sql

-- Add tenant_id column (nullable initially)
ALTER TABLE events
ADD COLUMN tenant_id UUID;

-- For existing data (if any), set a default tenant or require manual migration
-- UPDATE events SET tenant_id = '<some-default-uuid>' WHERE tenant_id IS NULL;

-- Make NOT NULL after backfill
ALTER TABLE events
ALTER COLUMN tenant_id SET NOT NULL;

-- Add index for performance
CREATE INDEX idx_events_tenant_aggregate
ON events(tenant_id, aggregate_uuid, playhead);

-- Add index for RLS queries
CREATE INDEX idx_events_tenant_id
ON events(tenant_id);
```

### Migration: Add tenant_id to read model

```sql
-- migrations/004_add_tenant_id_to_read_model.sql

ALTER TABLE gift_cards_read
ADD COLUMN tenant_id UUID NOT NULL;

CREATE INDEX idx_gift_cards_read_tenant
ON gift_cards_read(tenant_id);
```

---

## Component 4: PostgreSQL Row-Level Security (RLS)

### Enable RLS on tables

```sql
-- migrations/005_enable_rls.sql

-- Enable RLS on events table
ALTER TABLE events ENABLE ROW LEVEL SECURITY;

-- Enable RLS on read model
ALTER TABLE gift_cards_read ENABLE ROW LEVEL SECURITY;

-- Enable RLS on activation tokens
ALTER TABLE card_activation_tokens ENABLE ROW LEVEL SECURITY;

-- Enable RLS on webhook tables
ALTER TABLE webhook_subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE webhook_deliveries ENABLE ROW LEVEL SECURITY;

-- Enable RLS on audit log
ALTER TABLE audit_log ENABLE ROW LEVEL SECURITY;
```

### Create RLS Policies

**Policy 1: Tenant isolation policy**

```sql
-- Policy for events table
CREATE POLICY tenant_isolation_policy ON events
FOR ALL
TO PUBLIC
USING (
    tenant_id = current_setting('app.tenant_id', true)::uuid
    OR current_setting('app.admin_bypass', true) = 'true'
);

-- Policy for read model
CREATE POLICY tenant_isolation_policy ON gift_cards_read
FOR ALL
TO PUBLIC
USING (
    tenant_id = current_setting('app.tenant_id', true)::uuid
    OR current_setting('app.admin_bypass', true) = 'true'
);

-- Similar for other tables...
CREATE POLICY tenant_isolation_policy ON card_activation_tokens
FOR ALL
TO PUBLIC
USING (
    tenant_id = current_setting('app.tenant_id', true)::uuid
    OR current_setting('app.admin_bypass', true) = 'true'
);

CREATE POLICY tenant_isolation_policy ON webhook_subscriptions
FOR ALL
TO PUBLIC
USING (
    tenant_id = current_setting('app.tenant_id', true)::uuid
    OR current_setting('app.admin_bypass', true) = 'true'
);

CREATE POLICY tenant_isolation_policy ON webhook_deliveries
FOR ALL
TO PUBLIC
USING (
    tenant_id = current_setting('app.tenant_id', true)::uuid
    OR current_setting('app.admin_bypass', true) = 'true'
);

CREATE POLICY tenant_isolation_policy ON audit_log
FOR ALL
TO PUBLIC
USING (
    tenant_id = current_setting('app.tenant_id', true)::uuid
    OR tenant_id IS NULL -- admin/system actions
    OR current_setting('app.admin_bypass', true) = 'true'
);
```

**How it works:**
- `current_setting('app.tenant_id', true)` reads session variable
- `::uuid` casts string to UUID type
- `OR current_setting('app.admin_bypass', true) = 'true'` allows admin access
- `true` parameter = don't error if variable not set (returns NULL)

**Tables WITHOUT RLS:**
- `tenants` - admin only, no RLS needed
- `admin_users` - admin only
- `card_holders` - cross-tenant by design
- `magic_links` - cross-tenant by design
- `card_holder_sessions` - cross-tenant by design

---

## Component 5: Doctrine DBAL Middleware

**Purpose:** Set PostgreSQL session variable on every request

**Implementation:**

```php
namespace App\Infrastructure\Doctrine\Middleware;

use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Driver;
use App\Infrastructure\Tenant\Context\TenantContext;

class TenantContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->tenantContext) implements Driver {
            public function __construct(
                private Driver $innerDriver,
                private TenantContext $tenantContext,
            ) {}

            public function connect(array $params): Driver\Connection
            {
                $connection = $this->innerDriver->connect($params);

                // Set RLS context if tenant is set
                if ($this->tenantContext->hasTenantId()) {
                    $tenantId = $this->tenantContext->getTenantId()->toString();

                    $connection->exec("SET LOCAL app.tenant_id = '{$tenantId}'");

                    // Set admin bypass flag
                    $adminBypass = $this->tenantContext->isAdminBypass() ? 'true' : 'false';
                    $connection->exec("SET LOCAL app.admin_bypass = '{$adminBypass}'");
                }

                return $connection;
            }

            // Delegate other methods to inner driver
            public function getDatabasePlatform(): \Doctrine\DBAL\Platforms\AbstractPlatform
            {
                return $this->innerDriver->getDatabasePlatform();
            }

            public function getSchemaManager(\Doctrine\DBAL\Connection $conn, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): \Doctrine\DBAL\Schema\AbstractSchemaManager
            {
                return $this->innerDriver->getSchemaManager($conn, $platform);
            }
        };
    }
}
```

**Service registration:**

```yaml
# config/services.yaml

services:
    App\Infrastructure\Doctrine\Middleware\TenantContextMiddleware:
        arguments:
            - '@App\Infrastructure\Tenant\Context\TenantContext'
        tags:
            - { name: doctrine.middleware }
```

---

## Admin Bypass for Cross-Tenant Queries

**Use case:** Admin panel needs to view all tenants' data

**Implementation:**

```php
namespace App\Infrastructure\Admin\Service;

class AdminTenantAccess
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    /**
     * Execute callback with admin bypass enabled.
     * Use ONLY in admin panel after authorization check!
     */
    public function withAdminBypass(callable $callback): mixed
    {
        $wasEnabled = $this->tenantContext->isAdminBypass();

        try {
            $this->tenantContext->enableAdminBypass();
            return $callback();
        } finally {
            if (!$wasEnabled) {
                $this->tenantContext->clear();
            }
        }
    }
}
```

**Usage in admin controller:**

```php
namespace App\Interface\Admin\Controller;

class GiftCardAdminController
{
    public function searchAcrossAllTenants(Request $request): Response
    {
        // Verify admin is authenticated and authorized
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Execute cross-tenant query with bypass
        $cards = $this->adminTenantAccess->withAdminBypass(function() use ($request) {
            return $this->giftCardRepository->search(
                $request->query->get('query')
            );
        });

        return $this->render('admin/gift_cards.html.twig', [
            'cards' => $cards,
        ]);
    }
}
```

**IMPORTANT:** Only use in admin panel, never in public API!

---

## Security Testing

### Test 1: Cross-Tenant Load Protection

```php
class TenantIsolationTest extends KernelTestCase
{
    public function testTenantCannotLoadAnotherTenantsAggregate(): void
    {
        // Given: Two tenants
        $tenantA = $this->createTenant('Tenant A');
        $tenantB = $this->createTenant('Tenant B');

        // Tenant A creates a card
        $this->tenantContext->setTenantId($tenantA->getId());
        $cardId = $this->giftCardService->create(/* ... */);

        // Tenant B tries to load Tenant A's card
        $this->tenantContext->setTenantId($tenantB->getId());

        $this->expectException(TenantMismatchException::class);
        $this->expectExceptionMessage('Security violation');

        $this->giftCardRepository->load($cardId);
    }
}
```

### Test 2: Cross-Tenant Save Protection

```php
public function testTenantCannotSaveEventsForAnotherTenant(): void
{
    $tenantA = $this->createTenant('Tenant A');
    $tenantB = $this->createTenant('Tenant B');

    // Tenant A creates card
    $this->tenantContext->setTenantId($tenantA->getId());
    $card = GiftCard::create(/* ... */);

    // Tenant B tries to save events for Tenant A's card
    $this->tenantContext->setTenantId($tenantB->getId());

    $this->expectException(TenantMismatchException::class);

    $this->giftCardRepository->save($card);
}
```

### Test 3: RLS Blocks Direct SQL Queries

```php
public function testRlsBlocksCrossTenantSqlQuery(): void
{
    $tenantA = $this->createTenant('Tenant A');
    $tenantB = $this->createTenant('Tenant B');

    // Tenant A creates card
    $this->tenantContext->setTenantId($tenantA->getId());
    $cardA = $this->createGiftCard();

    // Tenant B creates card
    $this->tenantContext->setTenantId($tenantB->getId());
    $cardB = $this->createGiftCard();

    // Tenant B context active
    $connection = $this->getContainer()->get('doctrine.dbal.default_connection');

    // Try to query all events (should only see Tenant B's)
    $results = $connection->fetchAllAssociative('SELECT * FROM events');

    // Verify: Only Tenant B's events returned
    foreach ($results as $row) {
        $this->assertEquals($tenantB->getId()->toString(), $row['tenant_id']);
    }

    // Verify: Tenant A's events NOT returned
    $tenantAEventFound = false;
    foreach ($results as $row) {
        if ($row['aggregate_uuid'] === $cardA->getId()->toString()) {
            $tenantAEventFound = true;
            break;
        }
    }

    $this->assertFalse($tenantAEventFound, 'RLS failed: Tenant A event visible to Tenant B');
}
```

### Test 4: Admin Bypass Works

```php
public function testAdminCanAccessAllTenants(): void
{
    $tenantA = $this->createTenant('Tenant A');
    $tenantB = $this->createTenant('Tenant B');

    // Create cards for both tenants
    $this->tenantContext->setTenantId($tenantA->getId());
    $cardA = $this->createGiftCard();

    $this->tenantContext->setTenantId($tenantB->getId());
    $cardB = $this->createGiftCard();

    // Admin bypass enabled
    $this->tenantContext->enableAdminBypass();

    $connection = $this->getContainer()->get('doctrine.dbal.default_connection');
    $results = $connection->fetchAllAssociative('SELECT * FROM events');

    // Verify: Both tenants' events visible
    $tenants = array_unique(array_column($results, 'tenant_id'));
    $this->assertCount(2, $tenants);
    $this->assertContains($tenantA->getId()->toString(), $tenants);
    $this->assertContains($tenantB->getId()->toString(), $tenants);
}
```

---

## Performance Considerations

### Index Strategy

**Events table:**
```sql
-- Primary index (Broadway default)
CREATE UNIQUE INDEX events_uuid_playhead ON events(aggregate_uuid, playhead);

-- Tenant isolation index (for RLS queries)
CREATE INDEX idx_events_tenant_aggregate ON events(tenant_id, aggregate_uuid, playhead);

-- For cross-tenant admin queries
CREATE INDEX idx_events_tenant_id ON events(tenant_id);
```

**Read model:**
```sql
CREATE INDEX idx_gift_cards_read_tenant ON gift_cards_read(tenant_id);
CREATE INDEX idx_gift_cards_read_tenant_status ON gift_cards_read(tenant_id, status);
```

### Query Performance

**RLS overhead:**
- Minimal (~1-2ms per query)
- Index on `tenant_id` is critical
- PostgreSQL optimizer handles RLS efficiently

**Event loading:**
- TenantAwareEventStore adds ~0.5ms overhead (metadata check)
- Acceptable for typical use cases

**Optimization:**
- Partition events table by `tenant_id` if table grows > 10M rows
- Use table inheritance or declarative partitioning

### Partitioning Strategy (Future)

```sql
-- Create partitioned table (PostgreSQL 10+)
CREATE TABLE events_partitioned (
    id UUID NOT NULL,
    aggregate_uuid UUID NOT NULL,
    playhead INT NOT NULL,
    tenant_id UUID NOT NULL,
    payload JSONB NOT NULL,
    metadata JSONB NOT NULL,
    recorded_on TIMESTAMP NOT NULL,
    PRIMARY KEY (id, tenant_id)
) PARTITION BY HASH (tenant_id);

-- Create partitions (one per hash bucket)
CREATE TABLE events_partition_0 PARTITION OF events_partitioned
FOR VALUES WITH (MODULUS 4, REMAINDER 0);

CREATE TABLE events_partition_1 PARTITION OF events_partitioned
FOR VALUES WITH (MODULUS 4, REMAINDER 1);

-- etc.
```

**Benefits:**
- Faster queries (scan fewer rows)
- Easier maintenance (vacuum per partition)
- Scalability (add partitions as tenants grow)

**When to partition:**
- > 10 tenants with heavy usage
- > 10M events total
- Query performance degrades

---

## Monitoring & Alerting

### Security Alerts

**Alert on TenantMismatchException:**

```php
namespace App\Infrastructure\Tenant\EventListener;

class TenantMismatchListener implements EventSubscriberInterface
{
    public function onTenantMismatch(TenantMismatchException $exception): void
    {
        // Log to security audit
        $this->logger->critical('SECURITY VIOLATION: Cross-tenant access attempt', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Alert admin immediately
        $this->alertService->sendCriticalAlert(
            'Cross-tenant access attempt detected',
            $exception->getMessage()
        );

        // Optionally: suspend tenant temporarily
        if ($this->config->get('auto_suspend_on_violation')) {
            $this->tenantService->suspend($this->tenantContext->getTenantId());
        }
    }
}
```

### Metrics

**Track RLS queries:**

```sql
-- Enable query logging
ALTER DATABASE giftcard SET log_statement = 'all';

-- Or use pg_stat_statements extension
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Query: Find slow queries with RLS
SELECT
    query,
    calls,
    mean_exec_time,
    max_exec_time
FROM pg_stat_statements
WHERE query LIKE '%current_setting%'
ORDER BY mean_exec_time DESC
LIMIT 10;
```

**Application metrics:**

```php
$this->metrics->increment('tenant.isolation.check', [
    'tenant_id' => $tenantId,
    'result' => 'success',
]);

$this->metrics->histogram('event_store.load.duration', $duration, [
    'tenant_id' => $tenantId,
]);
```

---

## Troubleshooting

### Error: "TenantContext not initialized"

**Cause:** Trying to access repository without setting tenant context

**Solution:**
- Ensure HMAC middleware runs before controller
- Check service.yaml for correct middleware priority
- Verify TenantContext scope is 'request'

### Error: "RLS policy violation"

**Cause:** Query accessing data without `app.tenant_id` set

**Debug:**
```sql
-- Check current session variables
SELECT current_setting('app.tenant_id', true);
SELECT current_setting('app.admin_bypass', true);

-- Check RLS policies
SELECT * FROM pg_policies WHERE tablename = 'events';
```

**Solution:**
- Ensure Doctrine middleware executes
- Check PostgreSQL logs for RLS errors
- Verify RLS policies are created

### Performance: Slow queries

**Symptom:** Queries slow after enabling RLS

**Debug:**
```sql
EXPLAIN ANALYZE
SELECT * FROM events
WHERE aggregate_uuid = '<uuid>';
```

**Solution:**
- Add index on `(tenant_id, aggregate_uuid, playhead)`
- Consider partitioning for large tables

---

## Migration Path (Existing Data)

If you already have events without `tenant_id`:

**Step 1: Add column (nullable)**
```sql
ALTER TABLE events ADD COLUMN tenant_id UUID;
```

**Step 2: Backfill data**
```sql
-- Option A: Assign all to single tenant (migration)
UPDATE events SET tenant_id = '<default-tenant-uuid>' WHERE tenant_id IS NULL;

-- Option B: Derive from metadata (if stored)
UPDATE events
SET tenant_id = (metadata->>'tenant_id')::uuid
WHERE tenant_id IS NULL
AND metadata->>'tenant_id' IS NOT NULL;
```

**Step 3: Make NOT NULL**
```sql
ALTER TABLE events ALTER COLUMN tenant_id SET NOT NULL;
```

**Step 4: Enable RLS**
```sql
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation_policy ON events /* ... */;
```

---

## Best Practices

1. ✅ **Always set TenantContext** in middleware, never in controllers
2. ✅ **Use admin bypass sparingly** - only in admin panel
3. ✅ **Test cross-tenant isolation** in every feature
4. ✅ **Monitor for TenantMismatchException** - indicates attack or bug
5. ✅ **Use RLS as defense in depth** - don't rely on app code alone
6. ✅ **Index tenant_id columns** - critical for performance
7. ✅ **Partition events table** if > 10M rows
8. ✅ **Audit log all admin bypass usage**

---

## Checklist

Before going live:

- [ ] TenantContext service registered as request-scoped
- [ ] TenantAwareEventStore decorates DBALEventStore
- [ ] Doctrine middleware sets RLS session variables
- [ ] tenant_id column added to all tenant-scoped tables
- [ ] Indexes created on tenant_id columns
- [ ] RLS policies created on all tenant-scoped tables
- [ ] Cross-tenant isolation tests passing
- [ ] RLS SQL query test passing
- [ ] Admin bypass works for admin panel
- [ ] Security monitoring for TenantMismatchException
- [ ] Performance testing (query latency < 10ms)

---

**Last updated:** 2025-12-26
