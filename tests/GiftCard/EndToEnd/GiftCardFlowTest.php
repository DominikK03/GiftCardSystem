<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\EndToEnd;

use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\ValueObject\Address;
use App\Domain\Tenant\ValueObject\ApiKey;
use App\Domain\Tenant\ValueObject\ApiSecret;
use App\Domain\Tenant\ValueObject\NIP;
use App\Domain\Tenant\ValueObject\PhoneNumber;
use App\Domain\Tenant\ValueObject\RepresentativeName;
use App\Domain\Tenant\ValueObject\TenantEmail;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Domain\Tenant\ValueObject\TenantName;
use App\Infrastructure\Tenant\Security\HmacValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
class GiftCardFlowTest extends WebTestCase
{
    private HmacValidator $hmacValidator;
    private Tenant $tenant;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->hmacValidator = new HmacValidator();
    }
    public function test_full_gift_card_lifecycle(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE gift_cards_read CASCADE');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE tenants CASCADE');

        $this->tenant = $this->createAndPersistTenant();

        $createBody = json_encode([
            'amount' => 50000,
            'currency' => 'PLN',
            'expiresAt' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s+00:00'),
        ]);

        $this->makeAuthenticatedRequest($client, 'POST', '/api/gift-cards/create', $createBody);
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $createResponse);
        $giftCardId = $createResponse['id'];

        $this->makeAuthenticatedRequest($client, 'GET', "/api/gift-cards/{$giftCardId}");
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $giftCard = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('inactive', $giftCard['status']);
        $this->assertEquals(50000, $giftCard['balance']['amount']);
        $this->assertEquals('PLN', $giftCard['balance']['currency']);

        $this->makeAuthenticatedRequest($client, 'POST', "/api/gift-cards/{$giftCardId}/activate");

        $this->makeAuthenticatedRequest($client, 'GET', "/api/gift-cards/{$giftCardId}");
        $giftCard = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('active', $giftCard['status']);

        $redeemBody = json_encode(['amount' => 20000, 'currency' => 'PLN']);
        $this->makeAuthenticatedRequest($client, 'POST', "/api/gift-cards/{$giftCardId}/redeem", $redeemBody);

        $this->makeAuthenticatedRequest($client, 'GET', "/api/gift-cards/{$giftCardId}");
        $giftCard = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(30000, $giftCard['balance']['amount']);
        $this->assertEquals('active', $giftCard['status']);

        $redeemBody = json_encode(['amount' => 30000, 'currency' => 'PLN']);
        $this->makeAuthenticatedRequest($client, 'POST', "/api/gift-cards/{$giftCardId}/redeem", $redeemBody);

        $this->makeAuthenticatedRequest($client, 'GET', "/api/gift-cards/{$giftCardId}");
        $giftCard = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(0, $giftCard['balance']['amount']);
        $this->assertEquals('depleted', $giftCard['status']);
    }
    public function test_unauthenticated_request_is_blocked(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/gift-cards/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{"amount":10000,"currency":"PLN"}');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }
    public function test_suspended_tenant_cannot_access_api(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE tenants CASCADE');

        $this->tenant = $this->createAndPersistTenant();
        $this->tenant->suspend();
        $this->entityManager->flush();

        $body = json_encode(['amount' => 10000, 'currency' => 'PLN', 'expiresAt' => '2027-01-01T00:00:00+00:00']);
        $this->makeAuthenticatedRequest($client, 'POST', '/api/gift-cards/create', $body);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('suspended', $response['error']);
    }
    public function test_gift_card_history_returns_events(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE gift_cards_read CASCADE');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE tenants CASCADE');

        $this->tenant = $this->createAndPersistTenant();

        $createBody = json_encode([
            'amount' => 10000,
            'currency' => 'PLN',
            'expiresAt' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s+00:00'),
        ]);
        $this->makeAuthenticatedRequest($client, 'POST', '/api/gift-cards/create', $createBody);
        $giftCardId = json_decode($client->getResponse()->getContent(), true)['id'];

        $this->makeAuthenticatedRequest($client, 'GET', "/api/gift-cards/{$giftCardId}/history");
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $history = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('history', $history);
        $this->assertNotEmpty($history['history']);
        $this->assertGreaterThan(0, $history['totalEvents']);

        $firstEvent = $history['history'][0];
        $this->assertStringContainsString('GiftCardCreated', $firstEvent['event']['type']);
    }
    public function test_tenant_cannot_access_other_tenants_gift_cards(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE gift_cards_read CASCADE');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE tenants CASCADE');

        $this->tenant = $this->createAndPersistTenant('tenantA@example.com');

        $createBody = json_encode([
            'amount' => 10000,
            'currency' => 'PLN',
            'expiresAt' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s+00:00'),
        ]);
        $this->makeAuthenticatedRequest($client, 'POST', '/api/gift-cards/create', $createBody);
        $giftCardId = json_decode($client->getResponse()->getContent(), true)['id'];

        $tenantB = $this->createTenant('tenantB@example.com');
        $this->entityManager->persist($tenantB);
        $this->entityManager->flush();

        $timestamp = (string) time();
        $body = json_encode(['amount' => 5000, 'currency' => 'PLN']);
        $signature = $this->hmacValidator->calculateSignature(
            $tenantB->getId(), $timestamp, $body, $tenantB->getApiSecret()
        );

        $this->makeAuthenticatedRequest($client, 'POST', "/api/gift-cards/{$giftCardId}/activate");

        $client->request('POST', "/api/gift-cards/{$giftCardId}/redeem", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TENANT_ID' => $tenantB->getId()->toString(),
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $signature,
        ], $body);

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(Response::HTTP_OK, $statusCode);
        $this->assertNotEquals(Response::HTTP_ACCEPTED, $statusCode);
    }

    private function makeAuthenticatedRequest(
        $client,
        string $method,
        string $uri,
        string $body = ''
    ): void {
        $timestamp = (string) time();
        $signature = $this->hmacValidator->calculateSignature(
            $this->tenant->getId(), $timestamp, $body, $this->tenant->getApiSecret()
        );

        $client->request($method, $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TENANT_ID' => $this->tenant->getId()->toString(),
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $signature,
        ], $body);
    }

    private function consumeMessages($client): void
    {
        try {
            static::getContainer()->get('messenger.bus.default');
        } catch (\Throwable $e) {
        }
    }

    private function createAndPersistTenant(string $email = 'e2e-test@example.com'): Tenant
    {
        $tenant = $this->createTenant($email);
        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        return $tenant;
    }

    private function createTenant(string $email = 'test@example.com'): Tenant
    {
        return Tenant::create(
            TenantId::generate(),
            TenantName::fromString('E2E Test Company'),
            TenantEmail::fromString($email),
            NIP::fromString('1234567890'),
            Address::create('ul. Testowa 1', 'Warszawa', '00-001', 'Polska'),
            PhoneNumber::fromString('+48123456789'),
            RepresentativeName::create('Jan', 'Kowalski'),
            ApiKey::generate(),
            ApiSecret::generate()
        );
    }
}
