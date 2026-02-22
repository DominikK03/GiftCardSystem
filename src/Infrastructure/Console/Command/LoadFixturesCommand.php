<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Command;

use App\Application\GiftCard\Port\GiftCardReadModelWriterInterface;
use App\Application\GiftCard\ReadModel\GiftCardReadModel;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\Enum\GiftCardStatus;
use App\Domain\GiftCard\Port\GiftCardRepository;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\Port\TenantRepositoryInterface;
use App\Domain\Tenant\ValueObject\Address;
use App\Domain\Tenant\ValueObject\ApiKey;
use App\Domain\Tenant\ValueObject\ApiSecret;
use App\Domain\Tenant\ValueObject\NIP;
use App\Domain\Tenant\ValueObject\PhoneNumber;
use App\Domain\Tenant\ValueObject\RepresentativeName;
use App\Domain\Tenant\ValueObject\TenantEmail;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Domain\Tenant\ValueObject\TenantName;
use App\Domain\User\Entity\User;
use App\Domain\User\Port\UserRepository;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use App\Infrastructure\Tenant\TenantContext;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsCommand(
    name: 'app:load-fixtures',
    description: 'Load test fixtures for manual testing (tenants, users, gift cards)'
)]
final class LoadFixturesCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly UserRepository $userRepository,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly GiftCardRepository $giftCardRepository,
        private readonly GiftCardReadModelWriterInterface $readModelWriter,
        private readonly TenantContext $tenantContext,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Required to actually execute the command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')) {
            $io->error('This command will DELETE all existing data. Pass --force to confirm.');
            return Command::FAILURE;
        }

        $io->title('Loading test fixtures');

        $this->truncateTables($io);
        $tenantIds = $this->createTenants($io);
        $this->createUsers($io);
        $this->createGiftCards($io, $tenantIds);

        $io->success('Fixtures loaded successfully.');

        return Command::SUCCESS;
    }

    private function truncateTables(SymfonyStyle $io): void
    {
        $io->section('Truncating tables');

        $tables = ['card_assignments', 'activation_requests', 'gift_cards_read', 'events', 'users', 'tenants'];

        foreach ($tables as $table) {
            try {
                $this->connection->executeStatement(sprintf('TRUNCATE TABLE %s CASCADE', $table));
                $io->text(sprintf('  Truncated: %s', $table));
            } catch (\Doctrine\DBAL\Exception $e) {
                $io->text(sprintf('  Skipped: %s (does not exist)', $table));
            }
        }
    }

    /**
     * @return TenantId[]
     */
    private function createTenants(SymfonyStyle $io): array
    {
        $io->section('Creating tenants');

        $tenantsData = [
            [
                'name' => 'Acme Corp',
                'email' => 'acme@example.com',
                'nip' => '1234567890',
                'street' => 'ul. Marszałkowska 1',
                'city' => 'Warszawa',
                'postalCode' => '00-001',
                'country' => 'Polska',
                'phone' => '+48500100200',
                'firstName' => 'Jan',
                'lastName' => 'Kowalski',
                'allowedRedirectDomain' => 'example.com',
            ],
            [
                'name' => 'Beta Services',
                'email' => 'beta@example.com',
                'nip' => '0987654321',
                'street' => 'ul. Główna 10',
                'city' => 'Kraków',
                'postalCode' => '30-001',
                'country' => 'Polska',
                'phone' => '+48600300400',
                'firstName' => 'Anna',
                'lastName' => 'Nowak',
                'allowedRedirectDomain' => 'example.com',
            ],
        ];

        $tenantIds = [];

        foreach ($tenantsData as $data) {
            $tenantId = TenantId::generate();

            $tenant = Tenant::create(
                $tenantId,
                TenantName::fromString($data['name']),
                TenantEmail::fromString($data['email']),
                NIP::fromString($data['nip']),
                Address::create($data['street'], $data['city'], $data['postalCode'], $data['country']),
                PhoneNumber::fromString($data['phone']),
                RepresentativeName::create($data['firstName'], $data['lastName']),
                ApiKey::generate(),
                ApiSecret::generate(),
            );

            if (isset($data['allowedRedirectDomain'])) {
                $tenant->updateAllowedRedirectDomain($data['allowedRedirectDomain']);
            }

            $this->tenantRepository->save($tenant);
            $tenantIds[] = $tenantId;

            $io->text(sprintf('  Tenant: %s (%s)', $data['name'], $tenantId->toString()));
        }

        return $tenantIds;
    }

    private function createUsers(SymfonyStyle $io): void
    {
        $io->section('Creating users');

        $usersData = [
            ['email' => 'admin@giftcard.pl', 'password' => 'admin123', 'role' => 'OWNER'],
            ['email' => 'support@giftcard.pl', 'password' => 'admin123', 'role' => 'ADMIN'],
        ];

        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);

        foreach ($usersData as $data) {
            $user = User::register(
                UserId::generate(),
                UserEmail::fromString($data['email']),
                $hasher->hash($data['password']),
                UserRole::fromString($data['role']),
            );

            $this->userRepository->save($user);

            $io->text(sprintf('  User: %s (%s)', $data['email'], $data['role']));
        }
    }

    /**
     * @param TenantId[] $tenantIds
     */
    private function createGiftCards(SymfonyStyle $io, array $tenantIds): void
    {
        $io->section('Creating gift cards');

        foreach ($tenantIds as $tenantId) {
            $this->tenantContext->setTenantId($tenantId);

            $io->text(sprintf('  Tenant: %s', $tenantId->toString()));

            $this->createActiveCards($io, $tenantId, 3);
            $this->createInactiveCards($io, $tenantId, 2);
            $this->createPartiallyRedeemedCards($io, $tenantId, 2);
            $this->createSuspendedCard($io, $tenantId);
            $this->createCancelledCard($io, $tenantId);
            $this->createDepletedCard($io, $tenantId);
        }

        $this->tenantContext->clear();
    }

    private function createActiveCards(SymfonyStyle $io, TenantId $tenantId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $amount = $this->randomAmount();
            $giftCard = GiftCard::create(
                GiftCardId::generate(),
                $tenantId->toString(),
                $amount,
            );
            $giftCard->activate();
            $this->giftCardRepository->save($giftCard);

            $readModel = $this->saveReadModel(
                $giftCard->getAggregateRootId(),
                $tenantId->toString(),
                $amount->getAmount(),
                $amount->getAmount(),
                $amount->getCurrency(),
                GiftCardStatus::ACTIVE,
                $giftCard->getCardNumber()?->toString(),
                $giftCard->getPin()?->toString(),
            );
            $readModel->activatedAt = new DateTimeImmutable();
            $this->readModelWriter->save($readModel);

            $io->text(sprintf('    ACTIVE: %s PLN', $amount->getAmount() / 100));
        }
    }

    private function createInactiveCards(SymfonyStyle $io, TenantId $tenantId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $amount = $this->randomAmount();
            $giftCard = GiftCard::create(
                GiftCardId::generate(),
                $tenantId->toString(),
                $amount,
            );
            $this->giftCardRepository->save($giftCard);

            $this->saveReadModel(
                $giftCard->getAggregateRootId(),
                $tenantId->toString(),
                $amount->getAmount(),
                $amount->getAmount(),
                $amount->getCurrency(),
                GiftCardStatus::INACTIVE,
                $giftCard->getCardNumber()?->toString(),
                $giftCard->getPin()?->toString(),
            );

            $io->text(sprintf('    INACTIVE: %s PLN', $amount->getAmount() / 100));
        }
    }

    private function createPartiallyRedeemedCards(SymfonyStyle $io, TenantId $tenantId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $amount = $this->randomAmount();
            $redeemAmount = Money::fromPrimitives((int) ($amount->getAmount() * 0.4), $amount->getCurrency());

            $giftCard = GiftCard::create(
                GiftCardId::generate(),
                $tenantId->toString(),
                $amount,
            );
            $giftCard->activate();
            $giftCard->redeem($redeemAmount);
            $this->giftCardRepository->save($giftCard);

            $remainingBalance = $amount->getAmount() - $redeemAmount->getAmount();

            $readModel = $this->saveReadModel(
                $giftCard->getAggregateRootId(),
                $tenantId->toString(),
                $remainingBalance,
                $amount->getAmount(),
                $amount->getCurrency(),
                GiftCardStatus::ACTIVE,
                $giftCard->getCardNumber()?->toString(),
                $giftCard->getPin()?->toString(),
            );
            $readModel->activatedAt = new DateTimeImmutable();
            $this->readModelWriter->save($readModel);

            $io->text(sprintf(
                '    ACTIVE (redeemed): %s/%s PLN',
                $remainingBalance / 100,
                $amount->getAmount() / 100,
            ));
        }
    }

    private function createSuspendedCard(SymfonyStyle $io, TenantId $tenantId): void
    {
        $amount = $this->randomAmount();
        $giftCard = GiftCard::create(
            GiftCardId::generate(),
            $tenantId->toString(),
            $amount,
        );
        $giftCard->activate();
        $giftCard->suspend('Suspicious activity', 86400);
        $this->giftCardRepository->save($giftCard);

        $readModel = $this->saveReadModel(
            $giftCard->getAggregateRootId(),
            $tenantId->toString(),
            $amount->getAmount(),
            $amount->getAmount(),
            $amount->getCurrency(),
            GiftCardStatus::SUSPENDED,
            $giftCard->getCardNumber()?->toString(),
            $giftCard->getPin()?->toString(),
        );
        $readModel->activatedAt = new DateTimeImmutable();
        $readModel->suspendedAt = new DateTimeImmutable();
        $readModel->suspensionDuration = 86400;
        $this->readModelWriter->save($readModel);

        $io->text(sprintf('    SUSPENDED: %s PLN', $amount->getAmount() / 100));
    }

    private function createCancelledCard(SymfonyStyle $io, TenantId $tenantId): void
    {
        $amount = $this->randomAmount();
        $giftCard = GiftCard::create(
            GiftCardId::generate(),
            $tenantId->toString(),
            $amount,
        );
        $giftCard->activate();
        $giftCard->cancel('Customer requested cancellation');
        $this->giftCardRepository->save($giftCard);

        $readModel = $this->saveReadModel(
            $giftCard->getAggregateRootId(),
            $tenantId->toString(),
            $amount->getAmount(),
            $amount->getAmount(),
            $amount->getCurrency(),
            GiftCardStatus::CANCELLED,
            $giftCard->getCardNumber()?->toString(),
            $giftCard->getPin()?->toString(),
        );
        $readModel->activatedAt = new DateTimeImmutable();
        $readModel->cancelledAt = new DateTimeImmutable();
        $this->readModelWriter->save($readModel);

        $io->text(sprintf('    CANCELLED: %s PLN', $amount->getAmount() / 100));
    }

    private function createDepletedCard(SymfonyStyle $io, TenantId $tenantId): void
    {
        $amount = $this->randomAmount();
        $giftCard = GiftCard::create(
            GiftCardId::generate(),
            $tenantId->toString(),
            $amount,
        );
        $giftCard->activate();
        $giftCard->redeem($amount);
        $this->giftCardRepository->save($giftCard);

        $readModel = $this->saveReadModel(
            $giftCard->getAggregateRootId(),
            $tenantId->toString(),
            0,
            $amount->getAmount(),
            $amount->getCurrency(),
            GiftCardStatus::DEPLETED,
            $giftCard->getCardNumber()?->toString(),
            $giftCard->getPin()?->toString(),
        );
        $readModel->activatedAt = new DateTimeImmutable();
        $readModel->depletedAt = new DateTimeImmutable();
        $this->readModelWriter->save($readModel);

        $io->text(sprintf('    DEPLETED: 0/%s PLN', $amount->getAmount() / 100));
    }

    private function saveReadModel(
        string $id,
        string $tenantId,
        int $balanceAmount,
        int $initialAmount,
        string $currency,
        GiftCardStatus $status,
        ?string $cardNumber = null,
        ?string $pin = null,
    ): GiftCardReadModel {
        $now = new DateTimeImmutable();
        $expiresAt = new DateTimeImmutable('+1 year');

        $readModel = new GiftCardReadModel(
            $id,
            $tenantId,
            $balanceAmount,
            $currency,
            $initialAmount,
            $currency,
            $status->value,
            $now,
            $expiresAt,
            $cardNumber,
            $pin,
        );

        $this->readModelWriter->save($readModel);

        return $readModel;
    }

    private function randomAmount(): Money
    {
        $amounts = [5000, 10000, 15000, 20000, 25000, 30000, 35000, 40000, 45000, 50000];

        return Money::fromPrimitives($amounts[array_rand($amounts)], 'PLN');
    }
}
