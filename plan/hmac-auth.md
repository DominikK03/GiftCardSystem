# HMAC Authentication for Tenant API

## Overview

This document describes the HMAC-based authentication system for Tenant API access.

**Why HMAC over JWT/OAuth?**
- ✅ No token refresh needed
- ✅ Prevents replay attacks (nonce + timestamp)
- ✅ No external dependencies (no Auth server)
- ✅ Perfect for system-to-system communication
- ✅ Stateless (no session storage)

**Security properties:**
- Replay attack prevention (nonce)
- Timing attack prevention (hash_equals)
- Request tampering prevention (body hash)
- Credential theft mitigation (secret never transmitted)

---

## Authentication Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Tenant generates request                                     │
│    POST /v1/gift-cards                                          │
│    Body: {"amount": 100.00, "currency": "EUR", ...}             │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Tenant constructs signing string                            │
│    POST\n                                                       │
│    /v1/gift-cards\n                                             │
│    1735219200\n                                                 │
│    550e8400-e29b-41d4-a716-446655440000\n                      │
│    a3d5c7e9f1b2d4a6c8e0f2b4d6a8c0e2f4b6d8a0c2e4f6b8a0c2e4     │
│    (SHA256 of body)                                             │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. Tenant signs with HMAC-SHA256                               │
│    signature = HMAC-SHA256(signing_string, api_secret)         │
│    = "b8f3e7a5c9d1e3f5a7b9c1d3e5f7a9b1c3d5e7f9a1b3c5d7e9f1"   │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. Tenant sends request with headers                           │
│    POST /v1/gift-cards HTTP/1.1                                │
│    X-Tenant-Key: 123e4567-e89b-12d3-a456-426614174000          │
│    X-Signature: b8f3e7a5c9d1e3f5a7b9c1d3e5f7a9b1c3d5e7f9a1... │
│    X-Timestamp: 1735219200                                      │
│    X-Nonce: 550e8400-e29b-41d4-a716-446655440000               │
│    Content-Type: application/json                              │
│                                                                  │
│    {"amount": 100.00, "currency": "EUR", ...}                  │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Server validates request                                     │
│    ✓ Tenant exists (by X-Tenant-Key)                           │
│    ✓ Timestamp within window (±5 minutes)                     │
│    ✓ Nonce not used before (Redis check)                       │
│    ✓ Signature matches (reconstruct + compare)                 │
└─────────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. Server processes request                                     │
│    - Set TenantContext with tenant_id                          │
│    - Execute command handler                                    │
│    - Return response                                            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Signing String Format

**Format:**
```
METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY_SHA256
```

**Components:**

1. **METHOD:** HTTP method in UPPERCASE
   - Examples: `GET`, `POST`, `PUT`, `DELETE`

2. **PATH:** Request path (without query string, without domain)
   - Example: `/v1/gift-cards`
   - NOT: `https://api.giftcard.app/v1/gift-cards?foo=bar`

3. **TIMESTAMP:** UNIX epoch (seconds since 1970-01-01)
   - Example: `1735219200`
   - UTC timezone

4. **NONCE:** Unique identifier (UUID v4 recommended)
   - Example: `550e8400-e29b-41d4-a716-446655440000`
   - Must be globally unique

5. **BODY_SHA256:** SHA256 hash of request body (hex encoded)
   - Example: `a3d5c7e9f1b2d4a6c8e0f2b4d6a8c0e2f4b6d8a0c2e4f6b8a0c2e4`
   - For GET requests with no body: SHA256 of empty string = `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`

**Example:**
```
POST
/v1/gift-cards
1735219200
550e8400-e29b-41d4-a716-446655440000
a3d5c7e9f1b2d4a6c8e0f2b4d6a8c0e2f4b6d8a0c2e4f6b8a0c2e4
```

**Joined with newlines:**
```
POST\n/v1/gift-cards\n1735219200\n550e8400-e29b-41d4-a716-446655440000\na3d5c7e9f1b2d4a6c8e0f2b4d6a8c0e2f4b6d8a0c2e4f6b8a0c2e4
```

---

## Signature Generation

**Algorithm:** HMAC-SHA256

**PHP Implementation (Tenant Side):**

```php
namespace TenantClient;

class HmacSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
    ) {}

    public function signRequest(
        string $method,
        string $path,
        string $body = ''
    ): array {
        $timestamp = time();
        $nonce = $this->generateNonce();
        $bodyHash = hash('sha256', $body);

        $signingString = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $bodyHash,
        ]);

        $signature = hash_hmac('sha256', $signingString, $this->apiSecret);

        return [
            'X-Tenant-Key' => $this->apiKey,
            'X-Signature' => $signature,
            'X-Timestamp' => (string)$timestamp,
            'X-Nonce' => $nonce,
        ];
    }

    private function generateNonce(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
}
```

**Usage (Tenant Client):**

```php
$signer = new HmacSigner(
    apiKey: '123e4567-e89b-12d3-a456-426614174000',
    apiSecret: 'super-secret-key-never-transmitted'
);

$body = json_encode([
    'amount' => 10000, // 100.00 EUR in cents
    'currency' => 'EUR',
    'expires_at' => '2026-12-31T23:59:59Z',
]);

$headers = $signer->signRequest('POST', '/v1/gift-cards', $body);

// Send HTTP request
$response = $httpClient->post('https://api.giftcard.app/v1/gift-cards', [
    'headers' => array_merge($headers, [
        'Content-Type' => 'application/json',
    ]),
    'body' => $body,
]);
```

---

## Server-Side Validation

**Symfony Middleware:**

```php
namespace App\Infrastructure\Tenant\Http\Middleware;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class HmacAuthenticationMiddleware
{
    private const TIMESTAMP_TOLERANCE = 300; // 5 minutes in seconds

    public function __construct(
        private TenantApiCredentialRepository $credentialRepository,
        private NonceValidator $nonceValidator,
        private TenantContext $tenantContext,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Skip authentication for public endpoints
        if ($this->isPublicEndpoint($request->getPathInfo())) {
            return;
        }

        // Extract headers
        $apiKey = $request->headers->get('X-Tenant-Key');
        $signature = $request->headers->get('X-Signature');
        $timestamp = $request->headers->get('X-Timestamp');
        $nonce = $request->headers->get('X-Nonce');

        // Validate headers presence
        if (!$apiKey || !$signature || !$timestamp || !$nonce) {
            throw new UnauthorizedHttpException('HMAC', 'Missing authentication headers');
        }

        // 1. Find Tenant by API key
        $credential = $this->credentialRepository->findByApiKey($apiKey);
        if (!$credential) {
            throw new UnauthorizedHttpException('HMAC', 'Invalid API key');
        }

        // 2. Validate timestamp (±5 minutes)
        $this->validateTimestamp((int)$timestamp);

        // 3. Validate nonce (check Redis for duplicate)
        $this->nonceValidator->validate($nonce);

        // 4. Reconstruct signing string
        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $body = $request->getContent();
        $bodyHash = hash('sha256', $body);

        $signingString = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $bodyHash,
        ]);

        // 5. Verify signature (timing-safe comparison)
        $this->verifySignature($signingString, $signature, $credential);

        // 6. Set tenant context for RLS
        $this->tenantContext->setTenantId($credential->getTenantId());

        // 7. Mark nonce as used
        $this->nonceValidator->markUsed($nonce);
    }

    private function validateTimestamp(int $timestamp): void
    {
        $now = time();
        $diff = abs($now - $timestamp);

        if ($diff > self::TIMESTAMP_TOLERANCE) {
            throw new UnauthorizedHttpException(
                'HMAC',
                sprintf('Timestamp out of range. Server time: %d, Request time: %d', $now, $timestamp)
            );
        }
    }

    private function verifySignature(
        string $signingString,
        string $providedSignature,
        TenantApiCredential $credential
    ): void {
        // Try current secret
        $expectedSignature = hash_hmac('sha256', $signingString, $credential->getApiSecret());

        if (hash_equals($expectedSignature, $providedSignature)) {
            return; // Valid!
        }

        // Try previous secret (if rotated within 30 days)
        if ($credential->hasPreviousSecret()) {
            $expectedSignature = hash_hmac('sha256', $signingString, $credential->getPreviousApiSecret());

            if (hash_equals($expectedSignature, $providedSignature)) {
                return; // Valid with old secret!
            }
        }

        // Invalid signature
        throw new UnauthorizedHttpException('HMAC', 'Invalid signature');
    }

    private function isPublicEndpoint(string $path): bool
    {
        return in_array($path, [
            '/health',
            '/health/liveness',
            '/health/readiness',
        ]);
    }
}
```

---

## Nonce Validation (Redis)

**Purpose:** Prevent replay attacks

**Implementation:**

```php
namespace App\Infrastructure\Tenant\Security;

use Predis\Client as Redis;

class NonceValidator
{
    private const TTL = 600; // 10 minutes (2× timestamp tolerance)

    public function __construct(
        private Redis $redis,
    ) {}

    public function validate(string $nonce): void
    {
        $key = $this->getKey($nonce);

        if ($this->redis->exists($key)) {
            throw new ReplayAttackException('Nonce already used');
        }
    }

    public function markUsed(string $nonce): void
    {
        $key = $this->getKey($nonce);
        $this->redis->setex($key, self::TTL, '1');
    }

    private function getKey(string $nonce): string
    {
        return sprintf('hmac_nonce:%s', $nonce);
    }
}
```

**Why TTL = 10 minutes?**
- Timestamp tolerance = ±5 minutes
- Old nonce from 5 minutes ago could still be valid
- TTL must cover full timestamp window

---

## Credential Management

### Entity: TenantApiCredential

```php
namespace App\Domain\Tenant\Entity;

class TenantApiCredential
{
    private string $id;
    private TenantId $tenantId;
    private string $apiKey; // UUID, public identifier
    private string $apiSecretHash; // bcrypt hash
    private ?string $previousApiSecretHash; // for rotation overlap
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $rotatedAt;

    public static function generate(TenantId $tenantId): self
    {
        $credential = new self();
        $credential->id = Uuid::uuid4()->toString();
        $credential->tenantId = $tenantId;
        $credential->apiKey = Uuid::uuid4()->toString();

        // Generate random secret (32 bytes = 256 bits)
        $rawSecret = bin2hex(random_bytes(32));
        $credential->apiSecretHash = password_hash($rawSecret, PASSWORD_BCRYPT);

        $credential->createdAt = new \DateTimeImmutable();

        // IMPORTANT: Return raw secret ONCE (display to user, never stored)
        $credential->_tempRawSecret = $rawSecret;

        return $credential;
    }

    public function rotate(): string
    {
        // Move current secret to previous
        $this->previousApiSecretHash = $this->apiSecretHash;

        // Generate new secret
        $rawSecret = bin2hex(random_bytes(32));
        $this->apiSecretHash = password_hash($rawSecret, PASSWORD_BCRYPT);

        $this->rotatedAt = new \DateTimeImmutable();

        // Return raw secret for user
        return $rawSecret;
    }

    public function getApiSecret(): string
    {
        // Never returns hash! Used only for HMAC verification
        // This method should throw exception - secrets are hashed
        throw new \LogicException('Cannot retrieve hashed secret');
    }

    public function verifySecret(string $rawSecret): bool
    {
        return password_verify($rawSecret, $this->apiSecretHash);
    }

    public function hasPreviousSecret(): bool
    {
        if (!$this->previousApiSecretHash) {
            return false;
        }

        // Previous secret valid for 30 days
        if ($this->rotatedAt && $this->rotatedAt < new \DateTimeImmutable('-30 days')) {
            return false;
        }

        return true;
    }

    public function verifyPreviousSecret(string $rawSecret): bool
    {
        if (!$this->hasPreviousSecret()) {
            return false;
        }

        return password_verify($rawSecret, $this->previousApiSecretHash);
    }
}
```

**WAIT - Problem!** HMAC requires the raw secret, but we hash it! 🚨

**Solution:** Store secret encrypted, not hashed

```php
// Updated approach:

use Symfony\Component\Security\Core\Util\SecureRandom;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

class TenantApiCredential
{
    private string $apiSecretEncrypted; // Encrypted with app-level key
    private ?string $previousApiSecretEncrypted;

    public static function generate(TenantId $tenantId, Key $encryptionKey): self
    {
        $credential = new self();
        $credential->id = Uuid::uuid4()->toString();
        $credential->tenantId = $tenantId;
        $credential->apiKey = Uuid::uuid4()->toString();

        // Generate random secret
        $rawSecret = bin2hex(random_bytes(32));

        // Encrypt secret (not hash!)
        $credential->apiSecretEncrypted = Crypto::encrypt($rawSecret, $encryptionKey);

        $credential->createdAt = new \DateTimeImmutable();

        // Return raw secret once
        $credential->_tempRawSecret = $rawSecret;

        return $credential;
    }

    public function getApiSecret(Key $encryptionKey): string
    {
        return Crypto::decrypt($this->apiSecretEncrypted, $encryptionKey);
    }

    public function getPreviousApiSecret(Key $encryptionKey): string
    {
        if (!$this->previousApiSecretEncrypted) {
            throw new \LogicException('No previous secret');
        }

        return Crypto::decrypt($this->previousApiSecretEncrypted, $encryptionKey);
    }
}
```

**Encryption key:** Stored in `.env`
```
API_SECRET_ENCRYPTION_KEY=def00000... (generated with defuse/php-encryption)
```

---

## Credential Rotation

**Command:**

```php
namespace App\Application\Tenant\Command;

class RotateTenantCredentials
{
    public function __construct(
        public readonly TenantId $tenantId,
    ) {}
}
```

**Handler:**

```php
namespace App\Application\Tenant\Handler;

class RotateTenantCredentialsHandler
{
    public function handle(RotateTenantCredentials $command): array
    {
        $credential = $this->repository->findByTenantId($command->tenantId);

        if (!$credential) {
            throw new TenantNotFoundException();
        }

        // Generate new secret, keep old one
        $newRawSecret = $credential->rotate($this->encryptionKey);

        // Save
        $this->repository->save($credential);

        // Log to audit
        $this->auditLog->record([
            'actor_type' => 'admin',
            'action' => 'credentials.rotated',
            'resource_type' => 'tenant',
            'resource_id' => $command->tenantId,
        ]);

        // Send email to tenant
        $this->mailer->send(new CredentialsRotatedEmail(
            tenant: $credential->getTenant(),
            newApiKey: $credential->getApiKey(),
            newApiSecret: $newRawSecret,
        ));

        // Return new credentials (for admin UI)
        return [
            'api_key' => $credential->getApiKey(),
            'api_secret' => $newRawSecret, // Show ONCE, never again
            'rotated_at' => $credential->getRotatedAt(),
            'previous_secret_valid_until' => new \DateTimeImmutable('+30 days'),
        ];
    }
}
```

**Email to Tenant:**

```
Subject: API Credentials Rotated

Your API credentials have been rotated for security purposes.

NEW Credentials (use these immediately):
API Key:    123e4567-e89b-12d3-a456-426614174000
API Secret: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6

Old credentials will remain valid for 30 days (until 2026-01-25).

Update your integration ASAP!

IMPORTANT: Store API Secret securely. We cannot recover it.

Questions? Contact support@giftcard.app
```

---

## Security Best Practices

### 1. Timing Attack Prevention

**Problem:** `===` comparison leaks timing information

**Solution:** Use `hash_equals()`

```php
// ❌ WRONG (timing attack vulnerable)
if ($expectedSignature === $providedSignature) {
    // ...
}

// ✅ CORRECT (constant-time comparison)
if (hash_equals($expectedSignature, $providedSignature)) {
    // ...
}
```

### 2. Clock Skew Handling

**Problem:** Tenant server clock may be off

**Solution:** ±5 minute tolerance

```php
$diff = abs($serverTime - $requestTime);
if ($diff > 300) {
    throw new TimestampOutOfRangeException(
        "Your clock is off by {$diff} seconds. Please synchronize with NTP."
    );
}
```

### 3. Nonce Uniqueness

**Problem:** UUID v4 collision (extremely rare, but possible)

**Solution:** Check Redis before accepting

```php
if ($this->redis->exists("hmac_nonce:{$nonce}")) {
    // Log suspicious activity (possible attack!)
    $this->logger->warning('Nonce collision detected', [
        'nonce' => $nonce,
        'tenant_id' => $tenantId,
    ]);

    throw new ReplayAttackException();
}
```

### 4. Secret Storage

**Options:**

| Method | Pros | Cons |
|--------|------|------|
| Plaintext | Simple | 🚨 NEVER DO THIS |
| Hash (bcrypt) | Secure at rest | Cannot use for HMAC |
| Encrypt (AES-256) | Secure + usable | Requires key management ✅ |
| External vault (HashiCorp Vault) | Very secure | Overkill for thesis |

**Recommendation:** Encrypt with `defuse/php-encryption`

### 5. Error Messages

**Problem:** Error messages leak information

**Solution:** Generic messages, detailed logs

```php
// ❌ WRONG (leaks information)
throw new Exception('Invalid signature: expected abc123, got def456');

// ✅ CORRECT
throw new UnauthorizedHttpException('HMAC', 'Authentication failed');

// But log details server-side
$this->logger->warning('HMAC validation failed', [
    'tenant_id' => $tenantId,
    'expected' => $expectedSignature,
    'provided' => $providedSignature,
    'signing_string' => $signingString,
]);
```

---

## API Response Headers

**Success response:**

```
HTTP/1.1 200 OK
X-Tenant-ID: 789e0123-e45b-67d8-a901-234567890abc
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1735219260
Content-Type: application/json

{
  "id": "...",
  "status": "created"
}
```

**Authentication failure:**

```
HTTP/1.1 401 Unauthorized
WWW-Authenticate: HMAC realm="GiftCard API"
Content-Type: application/json

{
  "error": "authentication_failed",
  "message": "Invalid HMAC signature",
  "documentation": "https://docs.giftcard.app/authentication"
}
```

---

## Testing

### Unit Test: Signature Generation

```php
class HmacSignerTest extends TestCase
{
    public function testGeneratesCorrectSignature(): void
    {
        $signer = new HmacSigner(
            apiKey: 'test-key',
            apiSecret: 'test-secret'
        );

        $headers = $signer->signRequest('POST', '/v1/gift-cards', '{"foo":"bar"}');

        // Verify headers present
        $this->assertArrayHasKey('X-Tenant-Key', $headers);
        $this->assertArrayHasKey('X-Signature', $headers);
        $this->assertArrayHasKey('X-Timestamp', $headers);
        $this->assertArrayHasKey('X-Nonce', $headers);

        // Verify signature format (64 hex chars for SHA256)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $headers['X-Signature']);
    }
}
```

### Integration Test: Authentication Middleware

```php
class HmacAuthenticationTest extends WebTestCase
{
    public function testValidRequestIsAuthenticated(): void
    {
        $tenant = $this->createTenant();
        $credential = $this->createCredential($tenant);

        $body = json_encode(['amount' => 10000, 'currency' => 'EUR']);
        $headers = $this->generateHmacHeaders('POST', '/v1/gift-cards', $body, $credential);

        $this->client->request('POST', '/v1/gift-cards', [], [], $headers, $body);

        $this->assertResponseIsSuccessful();
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $tenant = $this->createTenant();
        $credential = $this->createCredential($tenant);

        $headers = [
            'HTTP_X_TENANT_KEY' => $credential->getApiKey(),
            'HTTP_X_SIGNATURE' => 'invalid-signature',
            'HTTP_X_TIMESTAMP' => (string)time(),
            'HTTP_X_NONCE' => Uuid::uuid4()->toString(),
        ];

        $this->client->request('POST', '/v1/gift-cards', [], [], $headers, '{}');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testReplayAttackIsBlocked(): void
    {
        $tenant = $this->createTenant();
        $credential = $this->createCredential($tenant);

        $nonce = Uuid::uuid4()->toString();

        // First request (valid)
        $headers = $this->generateHmacHeaders('POST', '/v1/gift-cards', '{}', $credential, $nonce);
        $this->client->request('POST', '/v1/gift-cards', [], [], $headers, '{}');
        $this->assertResponseIsSuccessful();

        // Second request with SAME nonce (replay attack)
        $headers = $this->generateHmacHeaders('POST', '/v1/gift-cards', '{}', $credential, $nonce);
        $this->client->request('POST', '/v1/gift-cards', [], [], $headers, '{}');
        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('Nonce already used', $this->client->getResponse()->getContent());
    }

    public function testExpiredTimestampIsRejected(): void
    {
        $tenant = $this->createTenant();
        $credential = $this->createCredential($tenant);

        $oldTimestamp = time() - 600; // 10 minutes ago

        $headers = $this->generateHmacHeaders('POST', '/v1/gift-cards', '{}', $credential, null, $oldTimestamp);
        $this->client->request('POST', '/v1/gift-cards', [], [], $headers, '{}');

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('Timestamp out of range', $this->client->getResponse()->getContent());
    }
}
```

---

## Client Libraries

### PHP Client Example

```php
namespace GiftCardClient;

class GiftCardApiClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $apiSecret,
        private HttpClient $httpClient,
    ) {}

    public function createGiftCard(
        int $amount,
        string $currency,
        \DateTimeInterface $expiresAt
    ): array {
        $body = json_encode([
            'amount' => $amount,
            'currency' => $currency,
            'expires_at' => $expiresAt->format(\DateTime::ATOM),
        ]);

        $response = $this->request('POST', '/v1/gift-cards', $body);

        return json_decode($response, true);
    }

    private function request(string $method, string $path, string $body = ''): string
    {
        $signer = new HmacSigner($this->apiKey, $this->apiSecret);
        $headers = $signer->signRequest($method, $path, $body);

        $response = $this->httpClient->request($method, $this->baseUrl . $path, [
            'headers' => array_merge($headers, [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            'body' => $body,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new ApiException(
                $response->getContent(false),
                $response->getStatusCode()
            );
        }

        return $response->getContent();
    }
}
```

**Usage:**

```php
$client = new GiftCardApiClient(
    baseUrl: 'https://api.giftcard.app',
    apiKey: $_ENV['GIFTCARD_API_KEY'],
    apiSecret: $_ENV['GIFTCARD_API_SECRET'],
    httpClient: HttpClient::create()
);

$card = $client->createGiftCard(
    amount: 10000, // 100.00 EUR in cents
    currency: 'EUR',
    expiresAt: new DateTime('+1 year')
);

echo "Card created: {$card['card_number']}\n";
echo "Activation code: {$card['activation_code']}\n";
```

---

## Troubleshooting

### Error: "Timestamp out of range"

**Cause:** Server and client clocks are not synchronized

**Solution:**
- Check server time: `date -u`
- Check client time: `date -u`
- Synchronize with NTP: `ntpdate pool.ntp.org`

### Error: "Nonce already used"

**Cause:** Replay attack or duplicate request

**Solution:**
- Check if you're retrying failed requests with same nonce
- Generate fresh nonce for each request
- Check Redis TTL is set correctly

### Error: "Invalid signature"

**Cause:** Signing string mismatch

**Debug:**
1. Log signing string on both sides
2. Compare character by character
3. Check for encoding issues (UTF-8)
4. Verify newline characters (`\n` not `\r\n`)

**Common mistakes:**
- Wrong HTTP method case (POST vs post)
- Query string included in path (/foo?bar=1 vs /foo)
- Body encoding (JSON pretty-print vs compact)
- Timezone (use UTC for timestamp)

---

## Performance Considerations

**Redis nonce check:**
- ~1ms per request
- Use Redis pipelining for batch operations
- Set appropriate TTL (10 min)

**HMAC computation:**
- ~0.1ms per request
- No significant bottleneck

**Expected latency:**
- HMAC validation: ~2ms total
- Acceptable for API use case

**Scaling:**
- Redis cluster for high throughput
- Rate limiting prevents abuse

---

**Last updated:** 2025-12-26
