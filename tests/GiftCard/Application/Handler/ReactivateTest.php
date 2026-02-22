<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Command\ReactivateCommand;
use App\Application\GiftCard\Handler\Reactivate;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReactivateTest extends TestCase
{
    public function test_it_reactivates_and_persists(): void
    {
        $provider = $this->createMock(GiftCardProviderInterface::class);
        $persister = $this->createMock(GiftCardPersisterInterface::class);
        $giftCard = $this->createMock(GiftCard::class);

        $id = GiftCardId::generate()->toString();
        $reactivatedAt = '2025-01-05T10:00:00+00:00';

        $provider
            ->expects($this->once())
            ->method('loadFromId')
            ->with($this->callback(fn (GiftCardId $giftCardId) => $giftCardId->toString() === $id))
            ->willReturn($giftCard);

        $giftCard
            ->expects($this->once())
            ->method('reactivate')
            ->with(
                'Reactivated by support',
                $this->callback(fn (DateTimeImmutable $date) => $date->format('c') === $reactivatedAt)
            );

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($giftCard);

        $handler = new Reactivate($provider, $persister);

        $handler->__invoke(new ReactivateCommand(
            id: $id,
            reason: 'Reactivated by support',
            reactivatedAt: $reactivatedAt
        ));
    }
}
