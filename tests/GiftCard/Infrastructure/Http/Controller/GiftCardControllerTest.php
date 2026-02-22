<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Infrastructure\Http\Controller;

use App\Application\GiftCard\Port\GiftCardReadModelWriterInterface;
use App\Application\GiftCard\ReadModel\GiftCardReadModel;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\Port\GiftCardRepository;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GiftCardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;
    private Tenant $tenant;
    private HmacValidator $hmacValidator;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->hmacValidator = new HmacValidator();

        $this->ensureSchema();
        $this->truncateTables();

        $this->tenant = $this->createAndPersistTenant();
    }

    public function test_create_returns_201_and_id(): void
    {
        $body = json_encode(['amount' => 1000, 'currency' => 'PLN']);
        $this->makeAuthenticatedRequest('POST', '/api/gift-cards/create', $body);

        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('created', $payload['status']);
        $this->assertTrue(Uuid::isValid($payload['id']));
    }

    public function test_create_returns_400_when_missing_required_fields(): void
    {
        $body = json_encode(['currency' => 'PLN']);
        $this->makeAuthenticatedRequest('POST', '/api/gift-cards/create', $body);

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_create_returns_400_when_invalid_json(): void
    {
        $this->makeAuthenticatedRequest('POST', '/api/gift-cards/create', '{invalid');

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_create_returns_400_when_currency_is_invalid(): void
    {
        $body = json_encode(['amount' => 1000, 'currency' => 'PLNN']);
        $this->makeAuthenticatedRequest('POST', '/api/gift-cards/create', $body);

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_create_returns_400_when_expires_at_is_invalid(): void
    {
        $body = json_encode(['amount' => 1000, 'currency' => 'PLN', 'expiresAt' => '2025-01-01']);
        $this->makeAuthenticatedRequest('POST', '/api/gift-cards/create', $body);

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_redeem_returns_202(): void
    {
        $id = $this->createGiftCardInEventStore();

        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/activate");

        $body = json_encode(['amount' => 100, 'currency' => 'PLN']);
        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/redeem", $body);

        $this->assertResponseStatusCodeSame(202);
    }

    public function test_redeem_returns_400_when_missing_required_fields(): void
    {
        $id = GiftCardId::generate()->toString();
        $body = json_encode(['amount' => 100]);

        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/redeem", $body);

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_activate_returns_202(): void
    {
        $id = $this->createGiftCardInEventStore();

        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/activate");

        $this->assertResponseStatusCodeSame(202);
    }

    public function test_suspend_returns_400_when_missing_required_fields(): void
    {
        $id = GiftCardId::generate()->toString();
        $body = json_encode(['reason' => 'Compliance']);

        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/suspend", $body);

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_adjust_balance_returns_400_when_missing_required_fields(): void
    {
        $id = GiftCardId::generate()->toString();
        $body = json_encode(['amount' => 100, 'currency' => 'PLN']);

        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/adjust-balance", $body);

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_decrease_balance_returns_202(): void
    {
        $id = $this->createGiftCardInEventStore();
        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/activate");

        $body = json_encode(['amount' => 100, 'currency' => 'PLN', 'reason' => 'Adjustment']);
        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/decrease-balance", $body);

        $this->assertResponseStatusCodeSame(202);
    }

    public function test_expire_returns_error_when_card_not_expired(): void
    {
        $id = $this->createGiftCardInEventStore();
        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/activate");

        $this->makeAuthenticatedRequest('POST', "/api/gift-cards/{$id}/expire", json_encode([]));

        $this->assertResponseStatusCodeSame(500);
        $payload = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('not expired yet', $payload['error']);
    }

    public function test_get_returns_404_when_missing(): void
    {
        $id = GiftCardId::generate()->toString();

        $this->makeAuthenticatedRequest('GET', "/api/gift-cards/{$id}");

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_get_returns_400_when_uuid_is_invalid(): void
    {
        $this->makeAuthenticatedRequest('GET', '/api/gift-cards/not-a-uuid');

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_get_returns_200_when_read_model_exists(): void
    {
        $id = GiftCardId::generate()->toString();
        $this->seedReadModel($id);

        $this->makeAuthenticatedRequest('GET', "/api/gift-cards/{$id}");

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame($id, $payload['id']);
        $this->assertSame('ACTIVE', $payload['status']);
    }

    public function test_list_returns_200_with_paginated_results(): void
    {
        $this->seedReadModel(GiftCardId::generate()->toString());
        $this->seedReadModel(GiftCardId::generate()->toString());
        $this->seedReadModel(GiftCardId::generate()->toString());

        $this->makeAuthenticatedRequest('GET', '/api/gift-cards');

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(3, $payload['giftCards']);
        $this->assertSame(3, $payload['total']);
        $this->assertSame(1, $payload['page']);
        $this->assertSame(20, $payload['limit']);
        $this->assertSame(1, $payload['totalPages']);
    }

    public function test_list_filters_by_status(): void
    {
        $this->seedReadModel(GiftCardId::generate()->toString(), 'ACTIVE');
        $this->seedReadModel(GiftCardId::generate()->toString(), 'ACTIVE');
        $this->seedReadModel(GiftCardId::generate()->toString(), 'CANCELLED');

        $this->makeAuthenticatedRequest('GET', '/api/gift-cards?status=CANCELLED');

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(1, $payload['giftCards']);
        $this->assertSame(1, $payload['total']);
        $this->assertSame('CANCELLED', $payload['giftCards'][0]['status']);
    }

    public function test_list_returns_empty_array_when_no_cards(): void
    {
        $this->makeAuthenticatedRequest('GET', '/api/gift-cards');

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(0, $payload['giftCards']);
        $this->assertSame(0, $payload['total']);
        $this->assertSame(0, $payload['totalPages']);
    }

    public function test_list_respects_pagination_params(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedReadModel(GiftCardId::generate()->toString());
        }

        $this->makeAuthenticatedRequest('GET', '/api/gift-cards?page=2&limit=2');

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(2, $payload['giftCards']);
        $this->assertSame(5, $payload['total']);
        $this->assertSame(2, $payload['page']);
        $this->assertSame(2, $payload['limit']);
        $this->assertSame(3, $payload['totalPages']);
    }

    public function test_history_returns_404_when_missing(): void
    {
        $id = GiftCardId::generate()->toString();

        $this->makeAuthenticatedRequest('GET', "/api/gift-cards/{$id}/history");

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_history_returns_200_when_events_exist(): void
    {
        $id = GiftCardId::generate();
        $giftCard = GiftCard::create(
            $id,
            $this->tenant->getId()->toString(),
            new Money(1000, 'PLN'),
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            new \DateTimeImmutable('2026-01-01T10:00:00+00:00')
        );

        /** @var GiftCardRepository $repository */
        $repository = static::getContainer()->get(GiftCardRepository::class);
        $repository->save($giftCard);

        $this->makeAuthenticatedRequest('GET', "/api/gift-cards/{$id->toString()}/history");

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame($id->toString(), $payload['giftCardId']);
        $this->assertSame(1, $payload['totalEvents']);
    }

    private function createGiftCardInEventStore(?\DateTimeImmutable $expiresAt = null): string
    {
        $id = GiftCardId::generate();
        $giftCard = GiftCard::create(
            $id,
            $this->tenant->getId()->toString(),
            new Money(1000, 'PLN'),
            new \DateTimeImmutable(),
            $expiresAt ?? new \DateTimeImmutable('+1 year')
        );

        /** @var GiftCardRepository $repository */
        $repository = static::getContainer()->get(GiftCardRepository::class);
        $repository->save($giftCard);

        return $id->toString();
    }

    private function makeAuthenticatedRequest(string $method, string $uri, string $body = ''): void
    {
        $timestamp = (string) time();
        $signature = $this->hmacValidator->calculateSignature(
            $this->tenant->getId(), $timestamp, $body, $this->tenant->getApiSecret()
        );

        $this->client->request($method, $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TENANT_ID' => $this->tenant->getId()->toString(),
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $signature,
        ], $body);
    }

    private function createAndPersistTenant(): Tenant
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $tenant = Tenant::create(
            TenantId::generate(),
            TenantName::fromString('Test Company'),
            TenantEmail::fromString('controller-test@example.com'),
            NIP::fromString('1234567890'),
            Address::create('ul. Testowa 1', 'Warszawa', '00-001', 'Polska'),
            PhoneNumber::fromString('+48123456789'),
            RepresentativeName::create('Jan', 'Kowalski'),
            ApiKey::generate(),
            ApiSecret::generate()
        );

        $entityManager->persist($tenant);
        $entityManager->flush();

        return $tenant;
    }

    private function seedReadModel(string $id, string $status = 'ACTIVE'): void
    {
        /** @var GiftCardReadModelWriterInterface $writer */
        $writer = static::getContainer()->get(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: $this->tenant->getId()->toString(),
            balanceAmount: 1000,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: $status,
            createdAt: new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: new \DateTimeImmutable('2026-01-01T10:00:00+00:00')
        );
        $readModel->activatedAt = new \DateTimeImmutable('2025-01-02T10:00:00+00:00');

        $writer->save($readModel);
    }

    private function ensureSchema(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['events'])) {
            $this->executeSqlFile('sql/001_create_events_table.sql');
        }
        if (!$schemaManager->tablesExist(['gift_cards_read'])) {
            $this->executeSqlFile('sql/003_create_gift_cards_read_table.sql');
        }
    }

    private function truncateTables(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['events'])) {
            $this->connection->executeStatement('TRUNCATE TABLE events');
        }
        if ($schemaManager->tablesExist(['gift_cards_read'])) {
            $this->connection->executeStatement('TRUNCATE TABLE gift_cards_read');
        }
        if ($schemaManager->tablesExist(['tenants'])) {
            $this->connection->executeStatement('TRUNCATE TABLE tenants CASCADE');
        }
    }

    private function executeSqlFile(string $path): void
    {
        $sql = file_get_contents($path);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement === '') {
                continue;
            }
            $this->connection->executeStatement($statement);
        }
    }
}
