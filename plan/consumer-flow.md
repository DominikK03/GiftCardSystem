# Consumer Flow (Card Holder Journey)

## Overview

This document describes the complete user journey for Card Holders (end consumers) who activate and use gift cards.

**Key Characteristics:**
- Passwordless authentication (magic link only)
- Cross-tenant support (one email can have cards from multiple tenants)
- GDPR compliant (data export, deletion)
- Fraud prevention (mark card as stolen)

---

## Flow 1: Card Activation (First Time)

### Prerequisites:
- Tenant has created a GiftCard via API
- Card is in INACTIVE status
- Card has:
  - `cardNumber` (16 digits, e.g., "1234-5678-9012-3456")
  - `activationCode` (12 digits, e.g., "123456789012")
  - `expiresAt` date set by Tenant

### User Journey:

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Card Holder receives physical/digital gift card             │
│    - Card number: 1234-5678-9012-3456                          │
│    - Activation code: 123456789012 (printed/emailed)           │
│    - Balance: 100.00 EUR                                        │
│    - Expires: 2026-12-31                                        │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Card Holder visits portal: https://portal.giftcard.app      │
│    - Landing page with activation form                         │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. Card Holder fills activation form                           │
│    [Card Number]:    1234-5678-9012-3456                       │
│    [Email]:          john.doe@example.com                      │
│    [Activation Code]: 123456789012                             │
│    [✓] I accept the Privacy Policy                             │
│    [Activate Card]                                              │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. System validates request                                     │
│    ✓ Card number exists                                         │
│    ✓ Activation code matches                                    │
│    ✓ Code not expired (< 1 year from creation)                 │
│    ✓ Card status = INACTIVE                                     │
│    ✓ Privacy policy accepted                                    │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. System processes activation                                  │
│    - Find or create CardHolder (by email)                      │
│    - Call GiftCard::activateByHolder(cardHolderId, email)      │
│    - Event: GiftCardActivatedByHolder emitted                  │
│    - Card status: INACTIVE → ACTIVE                             │
│    - Assign cardHolderId, cardHolderEmail                       │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. System sends confirmation email                             │
│    To: john.doe@example.com                                     │
│    Subject: "Your gift card has been activated!"               │
│    Content:                                                     │
│      - Card number: ****-****-****-3456                        │
│      - Balance: 100.00 EUR                                      │
│      - Expires: 2026-12-31                                      │
│      - Magic link (valid 15 min)                                │
│      - "Click to view your dashboard"                           │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 7. Card Holder clicks magic link in email                      │
│    URL: https://portal.giftcard.app/auth?token=<uuid>          │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 8. System validates magic link                                  │
│    ✓ Token exists in database                                   │
│    ✓ Token not expired (< 15 minutes)                          │
│    ✓ Token not used before                                      │
│    - Mark token as used                                         │
│    - Create CardHolderSession (24 hour expiry)                 │
│    - Set session cookie                                         │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 9. Redirect to dashboard                                        │
│    → /portal/dashboard                                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## Flow 2: Returning User (Request Magic Link)

Card Holder wants to check their cards after activation.

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Card Holder visits portal                                    │
│    https://portal.giftcard.app                                  │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Click "Already have a card? Sign in"                        │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. Enter email form                                             │
│    [Email]: john.doe@example.com                                │
│    [Send Magic Link]                                            │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. System validates                                             │
│    ✓ CardHolder with this email exists                         │
│    - Generate fresh MagicLink token                             │
│    - Send email with magic link                                 │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Success message displayed                                    │
│    "Magic link sent! Check your email."                         │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. Card Holder clicks link in email → Dashboard                │
│    (same validation as activation flow step 8)                 │
└─────────────────────────────────────────────────────────────────┘
```

**Security notes:**
- If email doesn't exist in system, still show success message (prevent email enumeration)
- Rate limit: max 3 magic link requests per email per hour

---

## Flow 3: Dashboard (Authenticated Session)

After successful magic link authentication:

```
┌─────────────────────────────────────────────────────────────────┐
│ DASHBOARD - john.doe@example.com                               │
├─────────────────────────────────────────────────────────────────┤
│ Your Gift Cards (2 total)                                       │
│                                                                  │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ Card: ****-****-****-3456                                 │  │
│ │ Balance: 85.00 EUR                                         │  │
│ │ Status: ACTIVE                                             │  │
│ │ Expires: 2026-12-31                                        │  │
│ │ [View History] [Mark as Stolen]                            │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ Card: ****-****-****-7890                                 │  │
│ │ Balance: 0.00 USD                                          │  │
│ │ Status: DEPLETED                                           │  │
│ │ Expires: 2025-06-30                                        │  │
│ │ [View History]                                             │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│ [Export My Data (GDPR)] [Delete My Account]                    │
│                                                                  │
│ Session expires in: 23h 45m                                     │
└─────────────────────────────────────────────────────────────────┘
```

**Data shown:**
- All cards assigned to this email (across ALL tenants)
- Per card:
  - Masked card number (last 4 digits visible)
  - Current balance
  - Status (ACTIVE, DEPLETED, EXPIRED, STOLEN)
  - Expiry date
  - Actions available

**Note:** Card Holder sees cards from multiple tenants (cross-tenant view)

---

## Flow 4: View Transaction History

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Card Holder clicks "View History" on a card                 │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. System loads all events for this GiftCard                   │
│    - Query events table by gift_card_id                        │
│    - Filter by event types relevant to Card Holder             │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ TRANSACTION HISTORY - Card ****-3456                           │
├─────────────────────────────────────────────────────────────────┤
│ 2025-12-20 10:30  ACTIVATED                                     │
│                   Card activated by you                         │
│                                                                  │
│ 2025-12-21 14:15  REDEEMED      -15.00 EUR                     │
│                   Balance: 85.00 EUR                            │
│                                                                  │
│ (Future events appear here as they happen)                     │
└─────────────────────────────────────────────────────────────────┘
```

**Events displayed:**
- ✅ GiftCardActivatedByHolder
- ✅ GiftCardRedeemed
- ✅ GiftCardBalanceAdjusted (if Admin adjusts)
- ✅ GiftCardSuspended (if Tenant/Admin suspends)
- ✅ GiftCardReactivated
- ✅ GiftCardExpired
- ✅ GiftCardDepleted
- ❌ GiftCardCreated (hidden, internal)

---

## Flow 5: Mark Card as Stolen

Card Holder lost their physical card or suspects fraud.

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Card Holder clicks "Mark as Stolen" on dashboard            │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Confirmation dialog                                          │
│    ⚠️  Are you sure you want to mark this card as stolen?      │
│                                                                  │
│    This will:                                                   │
│    - Immediately block all transactions                         │
│    - Prevent redemption until Admin unlocks it                 │
│                                                                  │
│    [Optional] Reason:                                           │
│    [Lost physical card / Suspected fraud / Other]              │
│                                                                  │
│    [Cancel] [Confirm - Mark as Stolen]                         │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. System processes                                             │
│    - Call GiftCard::markAsStolen(email, reason)                │
│    - Event: GiftCardMarkedAsStolen emitted                     │
│    - Card status: ACTIVE → STOLEN                               │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. System sends confirmation email                             │
│    Subject: "Your gift card has been marked as stolen"         │
│    Content:                                                     │
│      - Card number: ****-3456                                  │
│      - Reported at: 2025-12-22 16:45                           │
│      - Remaining balance: 85.00 EUR (frozen)                   │
│      - Next steps: Contact support to unlock                   │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Dashboard updated                                            │
│    Card status now shows: STOLEN                                │
│    Actions: [Contact Support] (no redeem possible)             │
└─────────────────────────────────────────────────────────────────┘
```

**Admin action required:**
- Card Holder must contact support
- Admin verifies identity
- Admin can unlock: STOLEN → ACTIVE (via admin panel)

---

## Flow 6: GDPR Data Export

Card Holder requests all their data (Right to Access).

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Card Holder clicks "Export My Data" on dashboard            │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Confirmation page                                            │
│    📄 Export Your Data (GDPR Right to Access)                  │
│                                                                  │
│    This will generate a report containing:                     │
│    - Your email and registration date                          │
│    - All gift cards assigned to you                            │
│    - Full transaction history                                  │
│                                                                  │
│    Format: [JSON] [PDF]                                        │
│    [Generate Export]                                            │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. System generates export                                      │
│    - Query CardHolder data                                      │
│    - Query all GiftCards for this email                        │
│    - Query all events for these cards                          │
│    - Generate JSON + PDF (Twig template)                       │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. Download link                                                │
│    ✅ Export generated successfully!                            │
│                                                                  │
│    [Download JSON] [Download PDF]                              │
│                                                                  │
│    Files will be available for 24 hours.                       │
└─────────────────────────────────────────────────────────────────┘
```

**JSON structure:**
```json
{
  "card_holder": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "john.doe@example.com",
    "created_at": "2025-12-20T10:30:00Z",
    "privacy_policy_accepted_at": "2025-12-20T10:30:00Z"
  },
  "gift_cards": [
    {
      "id": "...",
      "card_number": "1234567890123456",
      "balance": "85.00",
      "currency": "EUR",
      "status": "ACTIVE",
      "expires_at": "2026-12-31T23:59:59Z",
      "activated_at": "2025-12-20T10:30:00Z",
      "transactions": [
        {
          "type": "GiftCardActivatedByHolder",
          "timestamp": "2025-12-20T10:30:00Z",
          "data": {...}
        },
        {
          "type": "GiftCardRedeemed",
          "timestamp": "2025-12-21T14:15:00Z",
          "amount": "15.00",
          "balance_after": "85.00"
        }
      ]
    }
  ]
}
```

---

## Flow 7: GDPR Account Deletion

Card Holder requests account deletion (Right to be Forgotten).

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Card Holder clicks "Delete My Account" on dashboard         │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Warning page                                                 │
│    ⚠️  Delete Your Account (GDPR Right to be Forgotten)        │
│                                                                  │
│    WARNING: This action is IRREVERSIBLE!                       │
│                                                                  │
│    What will happen:                                            │
│    ✓ Your email will be anonymized                             │
│    ✓ You will lose access to all cards                         │
│    ✓ Cards with balance will remain usable IF you saved        │
│      card number + activation code                             │
│                                                                  │
│    Current card status:                                         │
│    - Card ****-3456: 85.00 EUR (ACTIVE) ⚠️ Has balance!       │
│    - Card ****-7890: 0.00 USD (DEPLETED) ✓ Safe to delete     │
│                                                                  │
│    Requirements:                                                │
│    ❌ Cannot delete: You have active cards with balance        │
│                                                                  │
│    [Cancel]                                                     │
└─────────────────────────────────────────────────────────────────┘
```

**Deletion allowed only if:**
- All cards are EXPIRED or DEPLETED (balance = 0)
- OR Card Holder explicitly confirms they want to lose access to balance

**If allowed:**
```
┌─────────────────────────────────────────────────────────────────┐
│ 3. Final confirmation                                           │
│    Type your email to confirm:                                  │
│    [john.doe@example.com]                                       │
│                                                                  │
│    [Cancel] [Confirm Deletion]                                 │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. System processes anonymization                               │
│    - Command: AnonymizeCardHolder(cardHolderId)                │
│    - Update events: email → deleted-<uuid>@anonymized.local    │
│    - Update read model: same                                    │
│    - Soft delete CardHolder: set deleted_at                    │
│    - Log to audit_log                                           │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Confirmation page                                            │
│    ✅ Your account has been deleted                             │
│                                                                  │
│    Your data has been anonymized.                              │
│    You have been logged out.                                    │
│                                                                  │
│    [Return to Home]                                             │
└─────────────────────────────────────────────────────────────────┘
```

**After anonymization:**
- Email in database: `deleted-550e8400-e29b-41d4-a716-446655440000@anonymized.local`
- Events still exist (audit trail)
- GiftCards remain usable IF Card Holder saved card_number + activation_code (can re-activate to new email)

---

## API Endpoints Summary

### Public (No Auth)

```
POST /portal/activate
Body:
{
  "card_number": "1234567890123456",
  "email": "john.doe@example.com",
  "activation_code": "123456789012",
  "accept_privacy_policy": true
}

Response 200:
{
  "success": true,
  "message": "Card activated successfully. Check your email for magic link.",
  "card_number": "****-****-****-3456"
}

Errors:
- 400: Invalid card number / activation code
- 404: Card not found
- 409: Card already activated
- 410: Activation code expired
- 422: Privacy policy not accepted
```

```
POST /portal/request-magic-link
Body:
{
  "email": "john.doe@example.com"
}

Response 200:
{
  "success": true,
  "message": "If this email is registered, a magic link has been sent."
}

Note: Always returns 200 even if email doesn't exist (prevent enumeration)
```

```
GET /portal/auth?token=<uuid>

Response:
- 302 Redirect to /portal/dashboard (with session cookie)
- 401 if token invalid/expired/used
```

### Authenticated (Magic Link Session)

```
GET /portal/dashboard

Response 200:
{
  "card_holder": {
    "email": "john.doe@example.com",
    "cards_count": 2
  },
  "cards": [
    {
      "id": "...",
      "card_number_masked": "****-****-****-3456",
      "balance": "85.00",
      "currency": "EUR",
      "status": "ACTIVE",
      "expires_at": "2026-12-31T23:59:59Z"
    }
  ]
}
```

```
GET /portal/cards/{id}/history

Response 200:
{
  "card_number_masked": "****-****-****-3456",
  "transactions": [
    {
      "type": "activated",
      "timestamp": "2025-12-20T10:30:00Z",
      "description": "Card activated by you"
    },
    {
      "type": "redeemed",
      "timestamp": "2025-12-21T14:15:00Z",
      "amount": "-15.00",
      "balance_after": "85.00"
    }
  ]
}
```

```
POST /portal/cards/{id}/mark-stolen
Body:
{
  "reason": "Lost physical card"
}

Response 200:
{
  "success": true,
  "message": "Card marked as stolen. Confirmation email sent."
}
```

```
GET /portal/export-data?format=json|pdf

Response 200:
- Content-Type: application/json or application/pdf
- Content-Disposition: attachment; filename="data-export-2025-12-22.json"
```

```
DELETE /portal/account
Body:
{
  "email_confirmation": "john.doe@example.com"
}

Response 200:
{
  "success": true,
  "message": "Account deleted and data anonymized."
}

Errors:
- 409: Cannot delete - active cards with balance exist
- 400: Email confirmation doesn't match
```

---

## Security Considerations

### Magic Link
- ✅ Token: UUID v4 (cryptographically random)
- ✅ Expiry: 15 minutes
- ✅ Single use: marked as used after click
- ✅ HTTPS only
- ✅ Rate limit: 3 requests per email per hour

### Session Management
- ✅ Session token: UUID v4
- ✅ Expiry: 24 hours
- ✅ HTTP-only cookie (prevents XSS)
- ✅ SameSite=Lax (CSRF protection)
- ✅ Secure flag in production

### Activation Code
- ✅ 12 digits = 10^12 combinations (brute force resistant)
- ✅ Expires after 1 year
- ✅ Single use
- ✅ Rate limit: 5 failed attempts → temp ban

### GDPR
- ✅ Anonymization instead of deletion (preserves audit trail)
- ✅ Cannot delete with active balance (prevents fraud)
- ✅ Data export includes all PII

### Cross-Tenant Security
- ✅ Card Holder can see cards from all tenants (by design)
- ✅ But cannot access tenant-specific admin functions
- ✅ RLS not applied to CardHolder table (cross-tenant by nature)

---

## Email Templates

**Locations:**
- `templates/email/card-activation-confirmation.html.twig`
- `templates/email/magic-link.html.twig`
- `templates/email/card-stolen-confirmation.html.twig`
- `templates/email/low-balance-warning.html.twig`
- `templates/email/card-expiring-soon.html.twig`

**Variables available in templates:**
- `card_number` (masked)
- `balance`
- `currency`
- `expires_at`
- `magic_link`
- `card_holder_email`
- etc.

---

## Rate Limiting

**Consumer Portal endpoints:**

| Endpoint | Limit | Scope | Penalty |
|----------|-------|-------|---------|
| POST /portal/activate | 5/hour | per IP | 1 hour ban |
| POST /portal/request-magic-link | 3/hour | per email | silent (prevent enum) |
| GET /portal/auth | 10/hour | per IP | 1 hour ban |
| POST /portal/cards/{id}/mark-stolen | 3/day | per card_holder | block |

**Implementation:** Token Bucket in Redis (see main plan)

---

## Testing Scenarios

**Happy path:**
1. Activate card → receive email → click magic link → see dashboard
2. Request magic link → receive email → click → see dashboard
3. View transaction history → all events displayed
4. Mark card as stolen → status updated → email sent

**Edge cases:**
1. Expired activation code → 410 Gone
2. Expired magic link → 401 Unauthorized
3. Used magic link (replay) → 401 Unauthorized
4. Delete account with active balance → 409 Conflict
5. Rate limit exceeded → 429 Too Many Requests

**Security:**
1. Email enumeration prevention (always 200 on magic link request)
2. Timing attack prevention (hash_equals for code comparison)
3. Session hijacking prevention (HTTP-only cookie + CSRF)
4. XSS prevention (all outputs escaped in Twig)
