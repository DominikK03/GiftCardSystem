<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Infrastructure\Console\Command;

use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ExpireGiftCardsCommandTest extends KernelTestCase
{
    private Connection $connection;
    private string $tenantId;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->connection = static::getContainer()->get(Connection::class);

        $this->ensureSchema();
        $this->truncateTables();

        $this->tenantId = $this->createAndPersistTenant();

        $application = new Application(self::$kernel);
        $command = $application->find('app:gift-card:expire-cards');
        $this->commandTester = new CommandTester($command);
    }

    public function test_expires_active_cards_past_expiration_date(): void
    {
        $id = $this->createActiveGiftCardInEventStoreAndReadModel(
            expiresAt: new \DateTimeImmutable('-1 day')
        );

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertSame(0, $this->commandTester->getStatusCode(), "Command failed with output: $output");
        $this->assertStringContainsString('Dispatched 1 expire command(s)', $output);

        $readModel = static::getContainer()->get(GiftCardReadModelRepositoryInterface::class)->findById($id);
        $this->assertSame('expired', $readModel->status);
    }

    public function test_does_not_expire_cards_with_future_expiration(): void
    {
        $id = $this->createActiveGiftCardInReadModel(
            expiresAt: new \DateTimeImmutable('+1 year')
        );

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('No gift cards to expire', $this->commandTester->getDisplay());

        $readModel = static::getContainer()->get(GiftCardReadModelRepositoryInterface::class)->findById($id);
        $this->assertSame('active', $readModel->status);
    }

    public function test_does_not_expire_non_active_cards(): void
    {
        $id = GiftCardId::generate()->toString();
        $this->seedReadModel($id, 'cancelled', new \DateTimeImmutable('-1 day'));

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('No gift cards to expire', $this->commandTester->getDisplay());
    }

    public function test_dry_run_does_not_dispatch_commands(): void
    {
        $id = $this->createActiveGiftCardInReadModel(
            expiresAt: new \DateTimeImmutable('-1 day')
        );

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Dry run mode', $this->commandTester->getDisplay());
        $this->assertStringContainsString($id, $this->commandTester->getDisplay());

        $readModel = static::getContainer()->get(GiftCardReadModelRepositoryInterface::class)->findById($id);
        $this->assertSame('active', $readModel->status);
    }

    public function test_handles_empty_result(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('No gift cards to expire', $this->commandTester->getDisplay());
    }

    private function createActiveGiftCardInReadModel(\DateTimeImmutable $expiresAt): string
    {
        $id = GiftCardId::generate()->toString();
        $this->seedReadModel($id, 'active', $expiresAt);

        return $id;
    }

    private function createActiveGiftCardInEventStoreAndReadModel(\DateTimeImmutable $expiresAt): string
    {
        $id = GiftCardId::generate();
        $giftCard = GiftCard::create(
            $id,
            $this->tenantId,
            new Money(10000, 'PLN'),
            new \DateTimeImmutable('-30 days'),
            $expiresAt
        );
        $giftCard->activate(new \DateTimeImmutable('-29 days'));

        /** @var GiftCardRepository $repository */
        $repository = static::getContainer()->get(GiftCardRepository::class);
        $repository->save($giftCard);

        return $id->toString();
    }

    private function seedReadModel(string $id, string $status, \DateTimeImmutable $expiresAt): void
    {
        /** @var GiftCardReadModelWriterInterface $writer */
        $writer = static::getContainer()->get(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: $this->tenantId,
            balanceAmount: 10000,
            balanceCurrency: 'PLN',
            initialAmount: 10000,
            initialCurrency: 'PLN',
            status: $status,
            createdAt: new \DateTimeImmutable('-30 days'),
            expiresAt: $expiresAt
        );

        if ($status === 'active') {
            $readModel->activatedAt = new \DateTimeImmutable('-29 days');
        }

        $writer->save($readModel);
    }

    private function createAndPersistTenant(): string
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $tenant = Tenant::create(
            TenantId::generate(),
            TenantName::fromString('Expire Test Company'),
            TenantEmail::fromString('expire-test@example.com'),
            NIP::fromString('1234567890'),
            Address::create('ul. Testowa 1', 'Warszawa', '00-001', 'Polska'),
            PhoneNumber::fromString('+48123456789'),
            RepresentativeName::create('Jan', 'Kowalski'),
            ApiKey::generate(),
            ApiSecret::generate()
        );

        $entityManager->persist($tenant);
        $entityManager->flush();

        return $tenant->getId()->toString();
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
