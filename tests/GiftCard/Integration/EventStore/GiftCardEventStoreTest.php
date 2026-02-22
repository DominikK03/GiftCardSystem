<?php

namespace App\Tests\GiftCard\Integration\EventStore;

use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\Port\GiftCardRepository;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GiftCardEventStoreTest extends KernelTestCase
{
    public function test_it_persists_events_and_rehydrates_aggregate(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $connection->executeStatement('TRUNCATE TABLE events');

        /** @var GiftCardRepository $repository */
        $repository = $container->get(GiftCardRepository::class);

        $id = GiftCardId::generate();
        $giftCard = GiftCard::create(
            $id,
            '550e8400-e29b-41d4-a716-446655440000',
            new Money(1000, 'PLN'),
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            new \DateTimeImmutable('2026-01-01T10:00:00+00:00')
        );

        $repository->save($giftCard);

        $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM events');
        $this->assertSame(1, $count);

        $rehydrated = $repository->load($id);
        $this->assertNotNull($rehydrated);
    }

}
