# GDPR Compliance Strategy

## Overview

This document outlines the GDPR compliance strategy for the GiftCard SaaS platform.

**Why GDPR applies:**
- Card Holder email = Personal Identifiable Information (PII)
- Transaction history linked to individual
- EU users likely (gift cards)

**Scope:**
- Card Holder data (email, transaction history)
- NOT Tenant data (company information, non-personal)
- NOT Admin data (internal users, separate policy)

---

## GDPR Principles

### 1. Lawfulness, Fairness, Transparency
✅ **Implemented:**
- Privacy policy displayed during activation
- Card Holder must explicitly accept before activation
- Clear data usage explained
- Contact information for data inquiries

### 2. Purpose Limitation
✅ **Implemented:**
- Email used ONLY for:
  - Card activation
  - Magic link authentication
  - Transaction notifications
  - GDPR requests
- NOT sold to third parties
- NOT used for marketing (unless Card Holder opts in separately)

### 3. Data Minimisation
✅ **Implemented:**
- Collect ONLY email (no name, address, phone unless required)
- No tracking cookies (only session cookie)
- No analytics on Card Holder data (unless anonymized)

### 4. Accuracy
✅ **Implemented:**
- Email verified during activation (magic link sent)
- Card Holder can update email (future feature)

### 5. Storage Limitation
✅ **Implemented:**
- Active cards: data kept indefinitely
- Expired/Depleted cards: **1 year retention**
- After 1 year: automatic anonymization
- Card Holder can request earlier deletion

### 6. Integrity and Confidentiality
✅ **Implemented:**
- HTTPS only (TLS 1.3)
- Email stored in database (PostgreSQL, encrypted at rest optional)
- Access control (RLS, authentication)
- Audit log for data access

### 7. Accountability
✅ **Implemented:**
- This document = GDPR compliance record
- Audit log tracks all data access/modifications
- Data Protection Impact Assessment (DPIA) if needed

---

## GDPR Rights Implementation

### Right 1: Right to be Informed

**What:** Card Holder must know how their data is used.

**Implementation:**

Privacy Policy (displayed during activation):
```
Privacy Policy

We collect and process your email address to:
1. Activate your gift card
2. Send you transaction notifications
3. Authenticate you via magic links

Your data is stored securely and NOT shared with third parties.

You have the right to:
- Access your data (export)
- Delete your data (anonymization)

Contact: privacy@giftcard.app

By clicking "Activate Card", you consent to this data processing.
```

**Location:** `templates/portal/privacy-policy.html.twig`

**Acceptance recorded:**
- `card_holders.privacy_policy_accepted_at` timestamp

---

### Right 2: Right of Access

**What:** Card Holder can request copy of their data.

**Implementation:**

**Endpoint:** `GET /portal/export-data?format=json|pdf`

**Auth:** Magic link session required

**Data included:**
```json
{
  "personal_information": {
    "email": "john.doe@example.com",
    "created_at": "2025-12-20T10:30:00Z",
    "privacy_policy_accepted_at": "2025-12-20T10:30:00Z"
  },
  "gift_cards": [
    {
      "card_number": "1234567890123456",
      "balance": "85.00 EUR",
      "status": "ACTIVE",
      "activated_at": "2025-12-20T10:30:00Z",
      "expires_at": "2026-12-31T23:59:59Z",
      "transactions": [...]
    }
  ]
}
```

**PDF format:**
- Human-readable report
- Generated with Twig template
- Includes all JSON data in formatted layout

**Delivery:**
- Instant download (no email)
- Files NOT stored server-side (ephemeral)

---

### Right 3: Right to Rectification

**What:** Card Holder can correct inaccurate data.

**Implementation:**

**Current:**
- Email is only personal data
- Email verified via magic link (assumed accurate)

**Future enhancement:**
- `PUT /portal/profile` to update email
- Re-verification required (send magic link to new email)
- Old email invalidated after confirmation

**Command:**
```php
class UpdateCardHolderEmail
{
    public function __construct(
        public readonly CardHolderId $cardHolderId,
        public readonly Email $newEmail,
    ) {}
}
```

**Event:**
```php
class CardHolderEmailUpdated
{
    public readonly CardHolderId $cardHolderId;
    public readonly Email $oldEmail;
    public readonly Email $newEmail;
    public readonly DateTime $updatedAt;
}
```

---

### Right 4: Right to Erasure (Right to be Forgotten)

**What:** Card Holder can request data deletion.

**Challenge:** Event Sourcing = immutable events!

**Solution:** Anonymization (not deletion)

#### Why Anonymization?

1. **Audit trail required** - Financial transactions must be kept for legal compliance
2. **Event Sourcing constraint** - Cannot delete events without breaking aggregate reconstruction
3. **GDPR allows it** - Anonymization = "effectively deleted" under GDPR

#### Anonymization Process:

**Endpoint:** `DELETE /portal/account`

**Requirements:**
- ✅ Email confirmation required
- ✅ Magic link session required
- ❌ BLOCKED if active cards with balance exist

**Steps:**

1. **Validate conditions:**
   ```php
   $activeCards = $this->cardRepository->findActiveWithBalanceByHolder($cardHolderId);

   if (count($activeCards) > 0) {
       throw new CannotDeleteWithActiveBalanceException(
           'You have active cards with balance. Please use cards or wait for expiry.'
       );
   }
   ```

2. **Anonymize email in events table:**
   ```sql
   -- Generate anonymized email
   SET @anonymized_email = CONCAT('deleted-', gen_random_uuid(), '@anonymized.local');

   -- Update event payloads (JSONB field)
   UPDATE events
   SET payload = jsonb_set(
       payload,
       '{holderEmail}',
       to_jsonb(@anonymized_email)
   )
   WHERE payload->>'cardHolderId' = '<card_holder_id>';
   ```

3. **Anonymize read model:**
   ```sql
   UPDATE gift_cards_read
   SET card_holder_email = @anonymized_email
   WHERE card_holder_id = '<card_holder_id>';
   ```

4. **Soft delete CardHolder:**
   ```sql
   UPDATE card_holders
   SET
       email = @anonymized_email,
       deleted_at = NOW(),
       privacy_policy_accepted_at = NULL
   WHERE id = '<card_holder_id>';
   ```

5. **Log to audit log:**
   ```php
   $this->auditLog->record([
       'actor_type' => 'card_holder',
       'actor_id' => $cardHolderId,
       'action' => 'account.deleted',
       'resource_type' => 'card_holder',
       'resource_id' => $cardHolderId,
       'metadata' => [
           'anonymized_email' => $anonymizedEmail,
           'cards_affected' => count($cards),
       ],
   ]);
   ```

6. **Invalidate session:**
   ```php
   $this->sessionManager->destroy($cardHolderId);
   ```

**Result:**
- Original email: `john.doe@example.com`
- Anonymized: `deleted-550e8400-e29b-41d4-a716-446655440000@anonymized.local`
- Events preserved (audit trail intact)
- Card Holder cannot login anymore
- GiftCards remain usable IF Card Holder saved card_number + activation_code

---

### Right 5: Right to Restrict Processing

**What:** Card Holder can ask to limit how data is used.

**Implementation:**

**Not applicable for this use case:**
- Email is REQUIRED for core functionality (activation, authentication)
- Cannot activate card without email
- Cannot authenticate without email

**If Card Holder wants to stop processing:**
- Delete account instead (anonymization)

---

### Right 6: Right to Data Portability

**What:** Card Holder can export data in machine-readable format.

**Implementation:**

✅ Same as Right of Access (JSON format)

**JSON structure** (machine-readable):
```json
{
  "$schema": "https://giftcard.app/schemas/data-export-v1.json",
  "export_date": "2025-12-22T10:00:00Z",
  "format_version": "1.0",
  "data": {
    "card_holder": {...},
    "gift_cards": [...]
  }
}
```

**Can be imported** to another system (if needed)

---

### Right 7: Right to Object

**What:** Card Holder can object to data processing.

**Implementation:**

**For marketing/profiling:** Not applicable (we don't do marketing)

**For core functionality:** Cannot object (email required for service)

**Solution:** Delete account if objecting to all processing

---

### Right 8: Rights Related to Automated Decision Making

**What:** Card Holder has rights if automated decisions affect them.

**Implementation:**

✅ **No automated decision making** in this system:
- No AI/ML models
- No credit scoring
- No profiling
- No automated rejections

All decisions are rule-based and transparent.

---

## Data Retention Policy

### Active Cards

**Retention:** Indefinite (as long as card is usable)

**Rationale:**
- Card Holder needs access to check balance
- Tenant may extend expiry date
- Legal requirement to keep financial records

### Expired/Depleted Cards

**Retention:** **1 year** after card becomes unusable

**Unusable = one of:**
- Status: EXPIRED
- Status: DEPLETED (balance = 0)
- Status: CANCELLED

**Automatic anonymization:**
- Cron job runs daily
- Finds Card Holders where ALL cards are unusable > 1 year
- Dispatches `AnonymizeCardHolder` command
- Logs to audit log

**Implementation:**

```php
// src/Infrastructure/GiftCard/Console/AnonymizeExpiredCardHoldersCommand.php

class AnonymizeExpiredCardHoldersCommand extends Command
{
    public function execute(): int
    {
        $cutoffDate = new DateTime('-1 year');

        // Find Card Holders eligible for anonymization
        $cardHolders = $this->repository->findEligibleForAnonymization($cutoffDate);

        foreach ($cardHolders as $cardHolder) {
            // Check all their cards
            $cards = $this->giftCardRepository->findByCardHolder($cardHolder->id);

            $allUnusable = true;
            foreach ($cards as $card) {
                if (!$card->isUnusable()) {
                    $allUnusable = false;
                    break;
                }
            }

            if ($allUnusable) {
                // Dispatch anonymization command
                $this->commandBus->dispatch(
                    new AnonymizeCardHolder($cardHolder->id)
                );

                $this->logger->info('Card Holder anonymized', [
                    'card_holder_id' => $cardHolder->id,
                    'cards_count' => count($cards),
                ]);
            }
        }

        return Command::SUCCESS;
    }
}
```

**Cron schedule:**
```yaml
# config/packages/scheduler.yaml
framework:
    scheduler:
        tasks:
            anonymize_expired_card_holders:
                command: 'app:anonymize-expired-card-holders'
                frequency: '0 2 * * *' # Daily at 2 AM
```

### Audit Logs

**Retention:** **1 year**

**After 1 year:**
- Archive to S3 (optional, not for thesis)
- Or delete (if no legal requirement)

---

## Data Security Measures

### Encryption

**At Rest:**
- PostgreSQL database on encrypted disk (provider-level)
- Optional: encrypt email column (Doctrine encrypted type)
  - Not implemented initially (email not highly sensitive)
  - Can add if required

**In Transit:**
- HTTPS only (TLS 1.3)
- Certificate from Let's Encrypt / CloudFlare

### Access Control

**Database:**
- RLS policies on tenant-scoped tables
- No RLS on `card_holders` (cross-tenant by design)
- Admin has full access (for GDPR requests)

**Application:**
- Card Holder can only access own data (session-based)
- Tenant cannot access Card Holder data (API limited to GiftCard operations)
- Admin can access all (for support/GDPR)

### Authentication

**Card Holder:**
- Magic link (short-lived, single-use)
- Session (24 hour expiry, HTTP-only cookie)

**Tenant:**
- HMAC signature (prevents credential theft)

**Admin:**
- Email + password + OTP (MFA)

### Audit Trail

**All Card Holder data access logged:**
```sql
INSERT INTO audit_log (actor_type, actor_id, action, resource_type, resource_id, metadata)
VALUES (
    'card_holder',
    '<card_holder_id>',
    'data.exported',
    'card_holder',
    '<card_holder_id>',
    '{"format": "json"}'
);
```

**Logged actions:**
- `data.exported` (GDPR export)
- `account.deleted` (GDPR deletion)
- `email.updated` (rectification)
- `card.marked_stolen` (security event)

---

## Data Breach Protocol

### Detection

**Indicators:**
- Unauthorized database access (PostgreSQL logs)
- Unusual API traffic (rate limit violations)
- Failed authentication attempts (brute force)

### Response (72 hours)

**If breach detected:**

1. **Contain (Hour 0):**
   - Disable affected accounts
   - Revoke API credentials
   - Block suspicious IPs

2. **Investigate (Hours 1-24):**
   - Check audit logs
   - Identify scope (how many Card Holders affected)
   - Determine data compromised

3. **Notify (Hours 24-72):**
   - If email addresses leaked: notify affected Card Holders
   - If payment data leaked: N/A (we don't store payment info)
   - Report to supervisory authority (if required)

4. **Remediate (Ongoing):**
   - Patch vulnerability
   - Reset all affected credentials
   - Improve security measures

**Email template:**
```
Subject: Important Security Notice

Dear Card Holder,

We are writing to inform you that on [DATE], we detected unauthorized
access to our system that may have affected your account.

Data potentially compromised:
- Email address: [EMAIL]
- Gift card transaction history

Data NOT compromised:
- No payment information (we don't store it)
- No passwords (we use passwordless authentication)

What we've done:
- Secured the vulnerability
- Enhanced monitoring
- Reported to authorities

What you should do:
- Be cautious of phishing emails
- Request a new magic link if concerned
- Contact support@giftcard.app with questions

We sincerely apologize for this incident.

[COMPANY]
```

---

## Privacy Policy Template

**Location:** `templates/portal/privacy-policy.html.twig`

**Content:**

```markdown
# Privacy Policy

Last updated: [DATE]

## 1. Who We Are

GiftCard SaaS Platform ("we", "us", "our")
Contact: privacy@giftcard.app

## 2. What Data We Collect

When you activate a gift card, we collect:
- Email address
- IP address (for security)
- Transaction history (redeems, balance changes)

We do NOT collect:
- Name, address, phone (unless you provide)
- Payment information (handled by tenants)
- Browsing data (no tracking cookies)

## 3. Why We Collect Data

Your email is used to:
- Activate your gift card
- Send you magic links for authentication
- Notify you of transactions
- Fulfill GDPR requests

## 4. Legal Basis (GDPR Article 6)

- Consent: You accept this policy during activation
- Legitimate interest: Fraud prevention, security

## 5. How Long We Keep Data

- Active cards: Until you delete your account
- Expired/depleted cards: 1 year, then anonymized
- Audit logs: 1 year

## 6. Your Rights

You can:
- Export your data (JSON/PDF)
- Delete your account (anonymization)
- Object to processing (delete account)
- Update your email (future feature)

## 7. Data Security

We use:
- HTTPS encryption (TLS 1.3)
- Secure database (PostgreSQL with RLS)
- Access control (magic links, MFA for admins)

## 8. Data Sharing

We do NOT:
- Sell your data to third parties
- Use your data for marketing (unless you opt in)
- Share with tenants (they only see aggregated data)

We MAY share:
- With law enforcement (if legally required)
- With service providers (email, hosting) under contract

## 9. Children's Privacy

Our service is not intended for children under 16.
If we discover data from a child, we will delete it.

## 10. International Transfers

Your data is stored in [REGION, e.g., EU].
If transferred outside EU, we use Standard Contractual Clauses (SCCs).

## 11. Changes to This Policy

We will notify you via email if we make material changes.

## 12. Contact Us

For GDPR requests or questions:
- Email: privacy@giftcard.app
- Response time: 30 days (GDPR requirement)

## 13. Supervisory Authority

If unsatisfied with our response, you can complain to:
[Your country's data protection authority]
```

---

## GDPR Compliance Checklist

**Before going live:**

- [ ] Privacy policy drafted and reviewed by legal (if possible)
- [ ] Privacy policy acceptance implemented in activation flow
- [ ] Data export endpoint implemented (JSON + PDF)
- [ ] Account deletion (anonymization) implemented
- [ ] 1-year retention cron job implemented
- [ ] Audit log records all Card Holder actions
- [ ] HTTPS enforced (no HTTP allowed)
- [ ] Session cookies: HTTP-only, Secure, SameSite
- [ ] Data breach response plan documented
- [ ] Contact email (privacy@...) set up
- [ ] Staff trained on GDPR procedures

**Optional (nice-to-have):**
- [ ] Data Protection Impact Assessment (DPIA) if high-risk
- [ ] Encrypt email column in database
- [ ] Third-party audit (penetration test)
- [ ] GDPR compliance seal (trustmark)

---

## Command Reference

### Anonymize Card Holder

```php
namespace App\Application\CardHolder\Command;

class AnonymizeCardHolder
{
    public function __construct(
        public readonly CardHolderId $cardHolderId,
    ) {}
}
```

**Handler:**
```php
namespace App\Application\CardHolder\Handler;

class AnonymizeCardHolderHandler
{
    public function handle(AnonymizeCardHolder $command): void
    {
        $cardHolder = $this->repository->find($command->cardHolderId);

        if (!$cardHolder) {
            throw new CardHolderNotFoundException();
        }

        // Generate anonymized email
        $anonymizedEmail = Email::anonymized($cardHolder->id);

        // Update events table
        $this->eventAnonymizer->anonymizeCardHolderEvents(
            $cardHolder->id,
            $anonymizedEmail
        );

        // Update read model
        $this->readModelUpdater->anonymizeCardHolder(
            $cardHolder->id,
            $anonymizedEmail
        );

        // Soft delete CardHolder entity
        $cardHolder->delete($anonymizedEmail);
        $this->repository->save($cardHolder);

        // Log to audit
        $this->auditLog->record([
            'actor_type' => 'system',
            'action' => 'card_holder.anonymized',
            'resource_type' => 'card_holder',
            'resource_id' => $cardHolder->id,
        ]);
    }
}
```

---

## Testing GDPR Compliance

### Test Scenarios:

**1. Data Export:**
```php
public function testCardHolderCanExportData(): void
{
    // Given: Card Holder with 2 cards
    $cardHolder = $this->createCardHolder('test@example.com');
    $card1 = $this->createAndActivateCard($cardHolder);
    $card2 = $this->createAndActivateCard($cardHolder);

    // When: Request export
    $response = $this->authenticatedRequest('GET', '/portal/export-data?format=json');

    // Then: Response contains all data
    $this->assertResponseIsSuccessful();
    $data = json_decode($response->getContent(), true);
    $this->assertEquals('test@example.com', $data['personal_information']['email']);
    $this->assertCount(2, $data['gift_cards']);
}
```

**2. Account Deletion (Happy Path):**
```php
public function testCardHolderCanDeleteAccountWhenAllCardsExpired(): void
{
    // Given: Card Holder with expired cards only
    $cardHolder = $this->createCardHolder('test@example.com');
    $card = $this->createExpiredCard($cardHolder);

    // When: Request deletion
    $response = $this->authenticatedRequest('DELETE', '/portal/account', [
        'email_confirmation' => 'test@example.com'
    ]);

    // Then: Account anonymized
    $this->assertResponseIsSuccessful();

    $cardHolder = $this->cardHolderRepository->find($cardHolder->id);
    $this->assertStringStartsWith('deleted-', $cardHolder->email);
    $this->assertNotNull($cardHolder->deletedAt);
}
```

**3. Account Deletion (Blocked):**
```php
public function testCardHolderCannotDeleteAccountWithActiveBalance(): void
{
    // Given: Card Holder with active card
    $cardHolder = $this->createCardHolder('test@example.com');
    $card = $this->createActiveCard($cardHolder, balance: 100.00);

    // When: Request deletion
    $response = $this->authenticatedRequest('DELETE', '/portal/account', [
        'email_confirmation' => 'test@example.com'
    ]);

    // Then: 409 Conflict
    $this->assertResponseStatusCodeSame(409);
    $this->assertStringContainsString('active cards with balance', $response->getContent());
}
```

**4. Retention Cron Job:**
```php
public function testRetentionCronAnonymizesExpiredCardHolders(): void
{
    // Given: Card Holder with card expired > 1 year ago
    $cardHolder = $this->createCardHolder('test@example.com');
    $card = $this->createCard($cardHolder, expiresAt: new DateTime('-13 months'));

    // When: Run cron job
    $this->runCommand('app:anonymize-expired-card-holders');

    // Then: Card Holder anonymized
    $cardHolder = $this->cardHolderRepository->find($cardHolder->id);
    $this->assertStringStartsWith('deleted-', $cardHolder->email);
}
```

---

## Frequently Asked Questions

**Q: Why anonymization instead of full deletion?**
A: Event Sourcing requires immutable events for audit trail. Anonymization satisfies GDPR while preserving system integrity.

**Q: Can Card Holder use card after account deletion?**
A: Yes, IF they saved card_number + activation_code. They can re-activate to a new email.

**Q: What if Card Holder forgets their email?**
A: No recovery possible (passwordless). They must know their email to request magic link.

**Q: Does GDPR apply to Tenant (company) data?**
A: No, company information is not PII. GDPR applies to Card Holder data only.

**Q: What about tenants who are sole proprietors (personal business)?**
A: If Tenant is an individual (not a company), their email IS PII. Handle separately from Card Holder flow.

**Q: Do we need a Data Protection Officer (DPO)?**
A: Only if processing at large scale or sensitive data. For thesis project, likely no. For production, consult legal.

---

## Resources

- [GDPR Official Text](https://gdpr-info.eu/)
- [ICO GDPR Guide](https://ico.org.uk/for-organisations/guide-to-data-protection/guide-to-the-general-data-protection-regulation-gdpr/)
- [GDPR.eu](https://gdpr.eu/)
- [Symfony Security Best Practices](https://symfony.com/doc/current/security.html)

---

**Last updated:** 2025-12-26
**Review schedule:** Every 6 months or when functionality changes
