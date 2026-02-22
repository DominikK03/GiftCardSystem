<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Command\CancelCommand;
use App\Application\GiftCard\Handler\Cancel;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CancelTest extends TestCase
{
    public function test_it_cancels_and_persists(): void
    {
        $provider = $this->createMock(GiftCardProviderInterface::class);
        $persister = $this->createMock(GiftCardPersisterInterface::class);
        $giftCard = $this->createMock(GiftCard::class);

        $id = GiftCardId::generate()->toString();
        $cancelledAt = '2025-01-03T10:00:00+00:00';

        $provider
            ->expects($this->once())
            ->method('loadFromId')
            ->with($this->callback(fn (GiftCardId $giftCardId) => $giftCardId->toString() === $id))
            ->willReturn($giftCard);

        $giftCard
            ->expects($this->once())
            ->method('cancel')
            ->with(
                'Customer requested',
                $this->callback(fn (DateTimeImmutable $date) => $date->format('c') === $cancelledAt)
            );

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($giftCard);

        $handler = new Cancel($provider, $persister);

        $handler->__invoke(new CancelCommand(
            id: $id,
            reason: 'Customer requested',
            cancelledAt: $cancelledAt
        ));
    }
}
