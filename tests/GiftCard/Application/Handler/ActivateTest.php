<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Command\ActivateCommand;
use App\Application\GiftCard\Handler\Activate;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ActivateTest extends TestCase
{
    public function test_it_activates_and_persists(): void
    {
        $provider = $this->createMock(GiftCardProviderInterface::class);
        $persister = $this->createMock(GiftCardPersisterInterface::class);
        $giftCard = $this->createMock(GiftCard::class);

        $id = GiftCardId::generate()->toString();
        $activatedAt = '2025-01-01T10:00:00+00:00';

        $provider
            ->expects($this->once())
            ->method('loadFromId')
            ->with($this->callback(fn (GiftCardId $giftCardId) => $giftCardId->toString() === $id))
            ->willReturn($giftCard);

        $giftCard
            ->expects($this->once())
            ->method('activate')
            ->with($this->callback(fn (DateTimeImmutable $date) => $date->format('c') === $activatedAt));

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($giftCard);

        $handler = new Activate($provider, $persister);

        $handler->__invoke(new ActivateCommand($id, $activatedAt));
    }
}
