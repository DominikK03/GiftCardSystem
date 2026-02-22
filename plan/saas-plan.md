# SaaS Multi-Tenant Plan (GiftCard)

## System Overview

This is a **B2B2C multi-tenant SaaS platform**:
- **Admin** (SaaS operator) - manages the entire system
- **Tenant** (company/client) - creates and manages gift cards via API
- **Card Holder** (end consumer) - activates and uses gift cards via web portal

### Key Assumptions:
- Tenant = company/client with API-only access (system-to-system)
- Card Holder = end consumer with web portal access (passwordless, magic link)
- Admin roles: Owner/Manager/Support (email + password + OTP)
- Full data isolation between tenants (RLS + tenant_id)
- GDPR compliance required (Card Holder email = PII)

## Recommended Architecture Choices

### 1) Tenant Isolation Strategy

Recommendation: **single database + tenant_id + PostgreSQL RLS**.

Why:
- Scales well for 10 tenants / 10k ops per day and beyond.
- Operationally simple (no per-tenant schema management).
- With RLS, isolation is enforced at DB level, not just app code.

Implementation summary:
- Add `tenant_id` to all tenant-owned tables (events, gift_cards_read, users, etc.).
- Add RLS policies on all tenant-owned tables.
- Enforce `SET app.tenant_id = '<tenant_uuid>'` per request/worker.

### 2) API Authentication for Tenants

Recommendation: **HMAC + timestamp + nonce**.

Why:
- Prevents replay attacks.
- Does not require external IdP.
- Scales for server-to-server use cases.

Mechanism:
- Each tenant gets `api_key` (public) + `api_secret` (private).
- Client signs request payload using `HMAC-SHA256`.
- Server verifies signature, timestamp window, and nonce uniqueness.

Suggested headers:
- `X-Tenant-Key`: public key
- `X-Signature`: HMAC signature
- `X-Timestamp`: UNIX epoch
- `X-Nonce`: random unique string

Signing string (example):
```
METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY_SHA256
```

Server checks:
- timestamp within 5 minutes
- nonce not used before (store in Redis with TTL)
- signature matches

### 3) Admin Panel Access

Recommendation: **separate frontend + separate auth flow** (not via tenant HMAC).

Why:
- Different audience, different security model.
- Avoid mixing tenant auth with admin auth.
- Allows MFA and admin-specific rate limits.

Admin auth:
- Email + password + email OTP.
- Add session-based auth (cookie + CSRF) for the panel.

### 4) Roles and Permissions

Admin-side roles (SaaS operator):
- Owner: full access
- Manager: manage tenants + cards, no system settings
- Support: read-only + limited actions (e.g. suspend cards)

Tenant-side roles:
- none (single tenant account, API key based)

### 5) Consumer Portal Access (Card Holder)

Recommendation: **passwordless magic link authentication**.

Why:
- No password management overhead
- Better UX for occasional use
- Secure (short-lived tokens)
- Simpler implementation

Flow:
1. Card Holder activates card: `card_number + email + 12-digit code`
2. System validates, activates card, sends magic link to email
3. Card Holder clicks magic link → authenticated session
4. Dashboard shows all cards for that email + history + balance

Subsequent access:
1. Card Holder enters email on portal
2. System sends fresh magic link
3. Click → authenticated

Magic link properties:
- Valid for 15 minutes
- Single use only
- Session valid for 24 hours

## Domain Model Extensions

### Entities

1) Tenant
- id (UUID)
- name
- status (ACTIVE/SUSPENDED)
- contact_email
- created_at

2) TenantApiCredential
- id
- tenant_id
- api_key
- api_secret_hash
- created_at
- rotated_at

3) AdminUser
- id
- email
- password_hash
- role (Owner/Manager/Support)
- is_active
- created_at

4) AdminUserOtp
- id
- admin_user_id
- otp_hash
- expires_at

5) CardHolder
- id (UUID)
- email (unique)
- email_verified (boolean)
- privacy_policy_accepted_at
- created_at
- deleted_at (nullable, for GDPR soft delete)

6) CardActivationToken
- id
- gift_card_id
- token (12-digit code, generated at card creation)
- expires_at (1 year from creation)
- used (boolean)
- used_at (nullable)

7) MagicLink
- id
- card_holder_id
- token (UUID)
- expires_at (15 minutes from generation)
- used (boolean)
- used_at (nullable)

8) CardHolderSession
- id
- card_holder_id
- session_token (UUID)
- expires_at (24 hours)
- ip_address
- user_agent
- created_at

### Multi-tenant changes

- Add tenant_id to:
  - events (critical for Event Sourcing isolation)
  - gift_cards_read (read model projection)
  - card_activation_tokens
  - webhook_subscriptions
  - webhook_deliveries
  - audit_log

- **IMPORTANT:** CardHolder, MagicLink, CardHolderSession are NOT tenant-scoped (cross-tenant)
  - Card Holder can have cards from multiple tenants
  - Identified by email, not tenant_id

- Update event store to store tenant_id with events (see Tenant Isolation section below).

### GiftCard Aggregate Extensions

Add new fields to `GiftCard`:
- `cardNumber` (CardNumber value object) - 16-digit friendly identifier
- `activationCode` (string) - 12-digit code, generated at creation
- `cardHolderId` (nullable) - UUID of assigned Card Holder
- `cardHolderEmail` (nullable) - Email of Card Holder
- `activatedByHolderAt` (nullable) - timestamp when Card Holder activated
- `markedAsStolenAt` (nullable) - timestamp when marked as stolen

### New Domain Events

**Consumer Portal events:**

1. `GiftCardActivatedByHolder`
   - giftCardId
   - cardHolderId
   - holderEmail
   - activatedAt

2. `GiftCardMarkedAsStolen`
   - giftCardId
   - reportedBy (email)
   - reason
   - markedAt

3. `GiftCardHolderAssigned` (when admin manually assigns)
   - giftCardId
   - cardHolderId
   - assignedBy (admin user id)
   - assignedAt

### Updated GiftCard Status Enum

Add new status: `STOLEN`

Complete enum:
- INACTIVE (created by Tenant, not yet activated by Card Holder)
- ACTIVE (activated by Card Holder)
- SUSPENDED (temporarily blocked by Admin or Tenant)
- DEPLETED (balance = 0)
- EXPIRED (expiresAt < now)
- CANCELLED (manually cancelled)
- STOLEN (marked as stolen by Card Holder)

**Transition rules:**
- INACTIVE → ACTIVE (only by Card Holder activation)
- ACTIVE → STOLEN (by Card Holder)
- STOLEN → ACTIVE (by Admin after verification)
- Tenant CANNOT activate card via API (only Card Holder can)

## API Authorization Changes

- All endpoints except /health require tenant auth.
- Middleware extracts tenant_id from HMAC key.
- Request context sets `app.tenant_id` for DB.

## Event Store Changes

**CRITICAL: Custom TenantAwareEventStore required**

Broadway's EventStore doesn't support multi-tenancy out of the box. We MUST wrap it.

### Implementation Strategy:

1. **TenantAwareEventStore decorator:**
   - Wraps Broadway's DBALEventStore
   - Automatically injects tenant_id from TenantContext on save()
   - Filters events by tenant_id on load()
   - **SECURITY:** Throws exception if tenant_id mismatch detected

2. **Database changes:**
   - Add `tenant_id UUID NOT NULL` column to `events` table
   - Add index on `(aggregate_uuid, tenant_id, playhead)`
   - Add RLS policy: `WHERE tenant_id = current_setting('app.tenant_id')::uuid`

3. **TenantContext service:**
   - Stores current tenant_id in request scope
   - Populated by HMAC middleware for API requests
   - Populated by admin session for admin requests
   - **CRITICAL:** Must be set before any repository operation

4. **Doctrine DBAL integration:**
   - Event listener on `postConnect` sets RLS context
   - Executes: `SET LOCAL app.tenant_id = '<uuid>'` per transaction
   - Ensures RLS policies are enforced at DB level

### Security Checklist:
- ✅ Tenant A cannot load aggregate from Tenant B
- ✅ Tenant A cannot save events with Tenant B's tenant_id
- ✅ RLS prevents data leakage even if app code fails
- ✅ Admin can access all tenants (bypass RLS with special flag)

## Admin Panel Features

Minimum scope:
- **Tenant management:**
  - CRUD operations
  - API credential rotation
  - Suspend/reactivate tenant

- **Gift card management:**
  - Search/filter (cross-tenant)
  - View card history (event timeline)
  - View Card Holder details
  - Manually assign card to Card Holder
  - Unreport stolen cards (STOLEN → ACTIVE)

- **Card Holder management:**
  - Search by email
  - View all cards per Card Holder
  - GDPR data export (JSON/PDF)
  - GDPR data deletion (anonymization)

- **Admin user management:**
  - CRUD admin users
  - Assign roles (Owner/Manager/Support)

- **Audit log viewer:**
  - Filter by actor, action, resource
  - Export audit logs

Nice-to-have:
- Rate limit dashboard
- Webhook management UI
- Email notification settings
- System health monitoring

## Consumer Portal Features

Card Holder can:
- **Activate card:**
  - Input: card_number + email + 12-digit activation code
  - Privacy policy acceptance required
  - Magic link sent to email

- **View dashboard (after magic link auth):**
  - All cards for this email (across all tenants)
  - Balance per card
  - Transaction history (event timeline)
  - Card status (ACTIVE/EXPIRED/DEPLETED/STOLEN)

- **Mark card as stolen:**
  - Input: reason (optional)
  - Card status → STOLEN
  - Card unusable until Admin unreports

- **Request magic link:**
  - Input: email only
  - Send fresh magic link

- **GDPR actions:**
  - Export all data (JSON/PDF)
  - Delete account (triggers anonymization)

## Email Notifications

**Card Holder notifications:**

1. **Card activation confirmed:**
   - Trigger: GiftCardActivatedByHolder
   - Content: card_number, balance, expiry_date, magic_link
   - Template: `card-activation-confirmation.html.twig`

2. **Low balance warning:**
   - Trigger: balance < 10% of original
   - Content: card_number, remaining_balance
   - Template: `low-balance-warning.html.twig`

3. **Card expiring soon:**
   - Trigger: 30 days before expiresAt
   - Content: card_number, balance, expiry_date
   - Template: `card-expiring-soon.html.twig`
   - Cron job: daily at 9 AM

4. **Card marked as stolen confirmed:**
   - Trigger: GiftCardMarkedAsStolen
   - Content: card_number, reported_at, next_steps
   - Template: `card-stolen-confirmation.html.twig`

5. **Magic link:**
   - Trigger: Card Holder requests login
   - Content: magic_link (expires in 15 min)
   - Template: `magic-link.html.twig`

**Admin notifications:**

1. **Webhook delivery failures:**
   - Trigger: 5 consecutive failures
   - Content: tenant, webhook_url, error
   - Template: `webhook-failure-alert.html.twig`

**Tenant notifications (optional):**
- Daily/weekly reports of card usage
- New Card Holder activations

**Implementation:**
- Symfony Mailer + async transport (RabbitMQ)
- Email templates in Twig
- Event Subscribers listen to domain events
- Send email commands to async queue

## Webhooks (Outbound)

Purpose: notify tenant systems about card changes.

**Subscribed events:**
- GiftCardCreated
- GiftCardActivatedByHolder
- GiftCardRedeemed
- GiftCardDepleted
- GiftCardExpired
- GiftCardSuspended
- GiftCardCancelled
- GiftCardMarkedAsStolen

**Webhook signing:**
- Each tenant has `webhook_secret`
- Payload signed with HMAC-SHA256
- Header: `X-Webhook-Signature: sha256=<signature>`
- Tenant verifies signature to prevent spoofing

**Delivery mechanism:**
- Async worker (Symfony Messenger)
- Retry strategy: 1min, 5min, 15min, 1h, 6h
- After 5 failures: mark as failed, alert admin
- Store delivery attempts in `webhook_deliveries` table

**Management:**
- Tenant can subscribe via API or Admin panel
- Admin can view delivery status per tenant

## GDPR Compliance

**Required because Card Holder email = PII (Personal Identifiable Information)**

### Right to Access (Data Export)

Card Holder can request data export:
- All GiftCards assigned to this email
- Transaction history (redeem events)
- Account creation date
- Export format: JSON + PDF

Implementation:
- Endpoint: `GET /portal/export-data`
- Auth: Magic link session
- Query events table + read model
- Generate PDF with Twig template

### Right to be Forgotten (Data Deletion)

Card Holder can delete account:
- **Problem:** Event Sourcing = immutable events, can't delete!
- **Solution:** Anonymization (crypto shredding would be overkill for thesis)

Anonymization process:
1. Replace `cardHolderEmail` with hash: `deleted-<uuid>@anonymized.local`
2. Update all events: replace email field with hash
3. Soft delete CardHolder: set `deleted_at`
4. Keep events intact (audit trail preserved)
5. Card remains usable IF Card Holder saves card_number + activation_code

Implementation:
- Command: `AnonymizeCardHolder(cardHolderId)`
- Updates events table (UPDATE events SET payload = ...)
- Updates read model
- **CAUTION:** Only anonymize if all cards EXPIRED or DEPLETED

### Data Retention Policy

- **Active cards:** Keep Card Holder data indefinitely
- **Expired/Depleted cards:** Keep for 1 year after last card expires
- **After 1 year:** Automatically anonymize via cron job

Cron job:
- Runs daily
- Finds Card Holders with all cards expired > 1 year
- Dispatches `AnonymizeCardHolder` command
- Logs to audit_log

### Privacy Policy

- Card Holder MUST accept privacy policy during activation
- Store acceptance timestamp: `privacy_policy_accepted_at`
- Display policy link on portal footer

## Audit Logging

**DO NOT duplicate domain events in audit log!**

Domain events already provide full audit trail. Audit log is for:

**What to log:**
- ✅ Admin actions (tenant CRUD, credential rotation, manual card assignment)
- ✅ Card Holder GDPR actions (data export, account deletion)
- ✅ Failed authentication attempts (HMAC, magic link, admin OTP)
- ✅ Rate limit violations
- ❌ Domain events (already in events table)
- ❌ Successful API calls (too much data, use metrics instead)

**Schema:**
```sql
CREATE TABLE audit_log (
    id UUID PRIMARY KEY,
    actor_type VARCHAR(20), -- 'admin' | 'card_holder' | 'system'
    actor_id UUID,
    tenant_id UUID NULL, -- NULL for admin/card_holder actions
    action VARCHAR(100), -- 'tenant.created', 'credentials.rotated'
    resource_type VARCHAR(50),
    resource_id UUID,
    metadata JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP
);

CREATE INDEX idx_audit_log_actor ON audit_log(actor_type, actor_id);
CREATE INDEX idx_audit_log_resource ON audit_log(resource_type, resource_id);
CREATE INDEX idx_audit_log_created ON audit_log(created_at DESC);
```

**Retention:** 1 year, then archive to S3 (optional for thesis)

## Rate Limiting

**Algorithm: Token Bucket** (best for API rate limiting)

### Configuration:

**Tenant API:**
- Bucket size: 100 tokens
- Refill rate: 60 tokens/minute (1 per second)
- Scope: per tenant (not per endpoint, simpler)

**Consumer Portal:**
- Bucket size: 30 tokens
- Refill rate: 20 tokens/minute
- Scope: per IP address

**Admin Panel:**
- Bucket size: 200 tokens
- Refill rate: 100 tokens/minute
- Scope: per admin user

### Implementation:

**Redis storage:**
```
Key: rate_limit:{scope}:{identifier}
Value: {tokens: 95, last_refill: 1735219200}
TTL: 60 seconds
```

**Response headers:**
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1735219260 (UNIX timestamp)
```

**Exceeded response:**
```
HTTP 429 Too Many Requests
Retry-After: 60 (seconds)

{
  "error": "rate_limit_exceeded",
  "message": "Too many requests. Please try again in 60 seconds.",
  "retry_after": 60
}
```

**Symfony integration:**
- Event Subscriber on `kernel.request`
- Extracts identifier (tenant_id / IP / admin_id)
- Checks/updates Redis bucket
- Throws `RateLimitExceededException` if exceeded
- Logs to audit_log on violation

## Implementation Phases

### Phase 0: Foundation & Architecture Design

**Goal:** Understand and design critical multi-tenant components

Tasks:
1. Design TenantAwareEventStore architecture
2. Design HMAC signing format and security flow
3. Design RLS integration with Doctrine DBAL
4. Design Consumer activation flow
5. Design GDPR anonymization strategy
6. Create detailed architecture diagrams

**Output:** Architecture decision records (ADRs) in docs/

**Duration estimate:** 1 week research + design

---

### Phase 1: Multi-tenant Foundation

**Goal:** Add tenant isolation at database and application level

Tasks:
1. Create Tenant entity (Domain + Infrastructure)
   - TenantId value object
   - Tenant aggregate
   - TenantRepository
   - Migrations

2. Add tenant_id to events table
   - Migration: ALTER TABLE events ADD tenant_id UUID NOT NULL
   - Add index on (aggregate_uuid, tenant_id, playhead)

3. Implement TenantContext service
   - Request-scoped service
   - Stores current tenant_id
   - Throws exception if not set

4. Implement TenantAwareEventStore
   - Decorator around DBALEventStore
   - Injects tenant_id on save()
   - Filters by tenant_id on load()
   - Security checks

5. Implement RLS policies
   - PostgreSQL RLS on events, gift_cards_read
   - Doctrine event listener for SET LOCAL app.tenant_id

6. Add tenant_id to Read Model
   - Migration: ALTER TABLE gift_cards_read ADD tenant_id UUID
   - Update projection logic

**Tests:**
- ✅ Tenant A cannot load Tenant B's aggregate
- ✅ Tenant A cannot save events with Tenant B's tenant_id
- ✅ RLS blocks cross-tenant queries
- ✅ Admin can bypass RLS (special flag)

**Duration estimate:** 2 weeks

---

### Phase 2: Tenant API (HMAC Authentication)

**Goal:** Secure API access for Tenants

Tasks:
1. Create TenantApiCredential entity
   - api_key (public, UUID)
   - api_secret_hash (bcrypt)
   - previous_api_secret_hash (for rotation)
   - Migrations

2. Implement HMAC signing
   - Signing string: METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY_SHA256
   - HMAC-SHA256 signature

3. Implement HMAC middleware
   - Extract headers: X-Tenant-Key, X-Signature, X-Timestamp, X-Nonce
   - Validate signature using hash_equals() (timing-safe)
   - Check timestamp window (±5 minutes)
   - Check nonce uniqueness (Redis)
   - Populate TenantContext

4. Implement nonce storage (Redis)
   - Key: hmac_nonce:{nonce}
   - TTL: 10 minutes

5. Implement credential rotation
   - Command: RotateTenantCredentials(tenantId)
   - Keep both secrets valid for 30 days
   - Notify tenant via email

6. Update GiftCard API endpoints
   - All except /health require HMAC auth
   - Return tenant_id in response headers

**Tests:**
- ✅ Valid signature → success
- ✅ Invalid signature → 401
- ✅ Replay attack (same nonce) → 401
- ✅ Expired timestamp → 401
- ✅ Timing attack resistance

**Duration estimate:** 2 weeks

---

### Phase 3: Consumer Portal (Card Holder)

**Goal:** Enable Card Holders to activate and manage gift cards

Tasks:
1. Create CardHolder entity
   - Email, privacy_policy_accepted_at
   - Soft delete support (deleted_at)
   - Migrations

2. Extend GiftCard aggregate
   - Add CardNumber value object (16 digits)
   - Add activationCode field (12 digits)
   - Add cardHolderId, cardHolderEmail
   - Add STOLEN status to enum
   - Implement activateByHolder() method
   - Implement markAsStolen() method

3. Generate activation code at card creation
   - Update Create command handler
   - Generate 12-digit code
   - Store in CardActivationToken table
   - Return in API response to Tenant

4. Implement magic link authentication
   - MagicLink entity (token, expires_at, used)
   - CardHolderSession entity (session management)
   - MagicLinkGenerator service
   - MagicLinkValidator service

5. Build activation flow
   - POST /portal/activate
   - Validate card_number + email + activation_code
   - Create/find CardHolder
   - Call activateByHolder()
   - Send confirmation email with magic link

6. Build portal dashboard
   - GET /portal/dashboard (requires magic link auth)
   - Show all cards for Card Holder's email
   - Show balance, status, history per card
   - Mark card as stolen button

7. Build magic link request flow
   - POST /portal/request-magic-link
   - Input: email only
   - Send fresh magic link if Card Holder exists

**Tests:**
- ✅ Valid activation → card ACTIVE
- ✅ Invalid code → error
- ✅ Expired code → error
- ✅ Card already activated → error
- ✅ Magic link expires after 15 min
- ✅ Magic link single-use only
- ✅ Mark as stolen → status STOLEN

**Duration estimate:** 3 weeks

---

### Phase 4: Email Notifications

**Goal:** Notify Card Holders and Admins of important events

Tasks:
1. Setup Symfony Mailer
   - Configure SMTP (Mailtrap for dev, SendGrid/Mailgun for prod)
   - Async email transport (RabbitMQ)

2. Create email templates (Twig)
   - card-activation-confirmation.html.twig
   - low-balance-warning.html.twig
   - card-expiring-soon.html.twig
   - card-stolen-confirmation.html.twig
   - magic-link.html.twig
   - webhook-failure-alert.html.twig

3. Create event subscribers
   - Listen to domain events (GiftCardActivatedByHolder, etc.)
   - Dispatch SendEmail command to async queue

4. Implement cron jobs
   - Daily: send expiring soon emails (30 days before)
   - Daily: trigger GDPR anonymization (1 year retention)

**Tests:**
- ✅ Activation sends email
- ✅ Low balance sends email
- ✅ Email sent to queue (not blocking)

**Duration estimate:** 1 week

---

### Phase 5: Admin Panel (Authentication + Core Features)

**Goal:** Admin can manage tenants, cards, and Card Holders

Tasks:
1. Create AdminUser entity
   - Email, password_hash, role enum
   - AdminUserOtp entity
   - Migrations

2. Implement admin authentication
   - Email + password (bcrypt)
   - Generate OTP, send via email
   - Validate OTP
   - Create session (cookie + CSRF token)

3. Implement RBAC
   - Roles: Owner, Manager, Support
   - Permissions per role
   - Authorization voters

4. Build admin UI (Twig + Symfony Forms)
   - Tenant CRUD
   - API credential rotation
   - Gift card search/filter (cross-tenant)
   - Card Holder search
   - View card event history

5. Admin-specific features
   - Manually assign card to Card Holder
   - Unreport stolen card (STOLEN → ACTIVE)
   - View webhook delivery status

**Tests:**
- ✅ OTP flow works
- ✅ Manager cannot access Owner features
- ✅ Support is read-only
- ✅ Admin can view cross-tenant data

**Duration estimate:** 3 weeks

---

### Phase 6: GDPR Compliance

**Goal:** Full GDPR compliance for Card Holder data

Tasks:
1. Implement data export
   - GET /portal/export-data (JSON + PDF)
   - Query all cards + events for Card Holder
   - Generate PDF report

2. Implement anonymization
   - Command: AnonymizeCardHolder(cardHolderId)
   - Update events payload (replace email with hash)
   - Update read model
   - Soft delete CardHolder

3. Implement data retention cron
   - Find Card Holders with all cards expired > 1 year
   - Dispatch AnonymizeCardHolder command
   - Log to audit_log

4. Privacy policy
   - Create privacy-policy.html.twig
   - Require acceptance during activation
   - Display link on portal footer

**Tests:**
- ✅ Export contains all Card Holder data
- ✅ Anonymization removes PII
- ✅ Events still exist (audit trail)
- ✅ Cron job triggers after 1 year

**Duration estimate:** 2 weeks

---

### Phase 7: Webhooks

**Goal:** Notify tenants of card events

Tasks:
1. Create WebhookSubscription entity
   - tenant_id, url, events[], webhook_secret
   - Migrations

2. Create WebhookDelivery entity
   - subscription_id, event_id, payload, signature
   - status, attempts, next_retry_at
   - Migrations

3. Implement webhook signing
   - HMAC-SHA256 of payload with webhook_secret
   - Header: X-Webhook-Signature

4. Implement delivery worker
   - Event subscriber dispatches to async queue
   - Worker sends HTTP POST to tenant URL
   - Retry on failure (1min, 5min, 15min, 1h, 6h)
   - Alert admin after 5 failures

5. Admin UI for webhooks
   - View subscriptions per tenant
   - View delivery status
   - Retry failed deliveries

**Tests:**
- ✅ Webhook signature is valid
- ✅ Retry works on failure
- ✅ Alert sent after 5 failures

**Duration estimate:** 2 weeks

---

### Phase 8: Rate Limiting & Production Readiness

**Goal:** Secure and scalable production deployment

Tasks:
1. Implement rate limiting
   - Token Bucket algorithm
   - Redis storage
   - Middleware per scope (tenant/IP/admin)
   - Response headers (X-RateLimit-*)

2. Implement audit logging
   - AuditLog entity
   - Event subscribers for admin/Card Holder actions
   - Retention policy (1 year)

3. API versioning
   - Move endpoints to /v1/
   - Versioning strategy documented

4. Health checks
   - GET /health/liveness
   - GET /health/readiness (DB, Redis, RabbitMQ)

5. Monitoring
   - Prometheus metrics export
   - Grafana dashboards (optional)

6. Security audit
   - OWASP top 10 checklist
   - SQL injection prevention
   - XSS prevention
   - CSRF tokens
   - HTTPS only

7. Load testing
   - Apache Bench / k6
   - Simulate 10 tenants × 1000 req/min
   - Identify bottlenecks

8. Documentation
   - API documentation (OpenAPI/Swagger)
   - Admin manual
   - Deployment guide

**Tests:**
- ✅ Rate limit enforced
- ✅ Health checks return correct status
- ✅ Load test passes
- ✅ Security audit passes

**Duration estimate:** 2 weeks

---

## Total Estimated Duration: 18 weeks (~4.5 months)

**Note:** This is a full-time estimate. Adjust for thesis project schedule.

## Critical Path:
Phase 1 → Phase 2 → Phase 3 (Consumer Portal is core functionality)

**Nice-to-have phases that can be deprioritized for thesis:**
- Phase 6 (GDPR) - implement basic anonymization, skip cron job
- Phase 7 (Webhooks) - skip if time is tight
- Phase 8 (Monitoring/Load testing) - do security audit, skip Prometheus

## Tests to Add

**Unit Tests:**
- Domain: GiftCard aggregate methods
- Value Objects: CardNumber, Email validation
- Services: HMAC signing, magic link generation

**Integration Tests:**
- Event Store: tenant isolation
- Repository: RLS enforcement
- Messenger: async command handling

**Functional Tests:**
- API: full HMAC auth flow
- Portal: activation flow
- Admin: OTP login flow

**Security Tests:**
- HMAC: replay attacks, timing attacks
- Magic Link: expiry, single-use
- RLS: cross-tenant access blocked
- Rate Limiting: enforcement

**E2E Tests (optional):**
- Full user journey: Tenant creates card → Card Holder activates → redeem → check balance
