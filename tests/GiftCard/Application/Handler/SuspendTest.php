<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Command\SuspendCommand;
use App\Application\GiftCard\Handler\Suspend;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SuspendTest extends TestCase
{
    public function test_it_suspends_and_persists(): void
    {
        $provider = $this->createMock(GiftCardProviderInterface::class);
        $persister = $this->createMock(GiftCardPersisterInterface::class);
        $giftCard = $this->createMock(GiftCard::class);

        $id = GiftCardId::generate()->toString();
        $suspendedAt = '2025-01-04T10:00:00+00:00';

        $provider
            ->expects($this->once())
            ->method('loadFromId')
            ->with($this->callback(fn (GiftCardId $giftCardId) => $giftCardId->toString() === $id))
            ->willReturn($giftCard);

        $giftCard
            ->expects($this->once())
            ->method('suspend')
            ->with(
                'Compliance check',
                3600,
                $this->callback(fn (DateTimeImmutable $date) => $date->format('c') === $suspendedAt)
            );

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($giftCard);

        $handler = new Suspend($provider, $persister);

        $handler->__invoke(new SuspendCommand(
            id: $id,
            reason: 'Compliance check',
            suspendedAt: $suspendedAt,
            suspensionDurationSeconds: 3600
        ));
    }
}
