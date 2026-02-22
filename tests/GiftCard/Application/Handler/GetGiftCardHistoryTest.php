<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Handler\GetGiftCardHistory;
use App\Application\GiftCard\Query\GetGiftCardHistoryQuery;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use Broadway\Domain\DomainEventStream;
use Broadway\EventStore\EventStore;
use PHPUnit\Framework\TestCase;

final class GetGiftCardHistoryTest extends TestCase
{
    public function test_it_returns_null_when_no_events(): void
    {
        $eventStore = $this->createMock(EventStore::class);
        $eventStore->expects($this->once())->method('load')->willReturn(new DomainEventStream([]));

        $handler = new GetGiftCardHistory($eventStore);

        $id = '11111111-1111-1111-1111-111111111111';
        $result = $handler->__invoke(new GetGiftCardHistoryQuery($id));

        $this->assertNull($result);
    }

    public function test_it_returns_history_when_events_exist(): void
    {
        $eventStore = $this->createMock(EventStore::class);

        $id = '11111111-1111-1111-1111-111111111111';
        $giftCard = GiftCard::create(
            GiftCardId::fromString($id),
            '550e8400-e29b-41d4-a716-446655440000',
            Money::fromPrimitives(1000, 'PLN'),
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            new \DateTimeImmutable('2026-01-01T10:00:00+00:00')
        );
        $stream = $giftCard->getUncommittedEvents();

        $eventStore->expects($this->once())->method('load')->willReturn($stream);

        $handler = new GetGiftCardHistory($eventStore);

        $result = $handler->__invoke(new GetGiftCardHistoryQuery($id));

        $this->assertNotNull($result);
        $payload = $result->toArray();
        $this->assertSame($id, $payload['giftCardId']);
        $this->assertSame(1, $payload['totalEvents']);
        $this->assertSame('GiftCardCreated', $payload['history'][0]['event']['type']);
    }
}
