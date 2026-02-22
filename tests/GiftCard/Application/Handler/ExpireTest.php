<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Command\ExpireCommand;
use App\Application\GiftCard\Handler\Expire;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ExpireTest extends TestCase
{
    public function test_it_expires_and_persists(): void
    {
        $provider = $this->createMock(GiftCardProviderInterface::class);
        $persister = $this->createMock(GiftCardPersisterInterface::class);
        $giftCard = $this->createMock(GiftCard::class);

        $id = GiftCardId::generate()->toString();
        $expiredAt = '2025-12-31T23:59:59+00:00';

        $provider
            ->expects($this->once())
            ->method('loadFromIdAsSystem')
            ->with($this->callback(fn (GiftCardId $giftCardId) => $giftCardId->toString() === $id))
            ->willReturn($giftCard);

        $giftCard
            ->expects($this->once())
            ->method('expire')
            ->with(
                $this->callback(fn (DateTimeImmutable $date) => $date->format('c') === $expiredAt)
            );

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($giftCard);

        $handler = new Expire($provider, $persister);

        $handler->__invoke(new ExpireCommand(
            id: $id,
            expiredAt: $expiredAt
        ));
    }
}
