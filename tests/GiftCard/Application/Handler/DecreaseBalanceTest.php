<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Command\DecreaseBalanceCommand;
use App\Application\GiftCard\Handler\DecreaseBalance;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DecreaseBalanceTest extends TestCase
{
    public function test_it_decreases_balance_and_persists(): void
    {
        $provider = $this->createMock(GiftCardProviderInterface::class);
        $persister = $this->createMock(GiftCardPersisterInterface::class);
        $giftCard = $this->createMock(GiftCard::class);

        $id = GiftCardId::generate()->toString();
        $decreasedAt = '2025-01-06T10:00:00+00:00';

        $provider
            ->expects($this->once())
            ->method('loadFromId')
            ->with($this->callback(fn (GiftCardId $giftCardId) => $giftCardId->toString() === $id))
            ->willReturn($giftCard);

        $giftCard
            ->expects($this->once())
            ->method('decreaseBalance')
            ->with(
                $this->callback(fn (Money $money) => $money->getAmount() === 250 && $money->getCurrency() === 'PLN'),
                'Correction',
                $this->callback(fn (DateTimeImmutable $date) => $date->format('c') === $decreasedAt)
            );

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($giftCard);

        $handler = new DecreaseBalance($provider, $persister);

        $handler->__invoke(new DecreaseBalanceCommand(
            id: $id,
            amount: 250,
            currency: 'PLN',
            reason: 'Correction',
            decreasedAt: $decreasedAt
        ));
    }
}
