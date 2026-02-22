<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Command\AdjustBalanceCommand;
use App\Application\GiftCard\Handler\AdjustBalance;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AdjustBalanceTest extends TestCase
{
    public function test_it_adjusts_balance_and_persists(): void
    {
        $provider = $this->createMock(GiftCardProviderInterface::class);
        $persister = $this->createMock(GiftCardPersisterInterface::class);
        $giftCard = $this->createMock(GiftCard::class);

        $id = GiftCardId::generate()->toString();
        $adjustedAt = '2025-01-02T10:00:00+00:00';

        $provider
            ->expects($this->once())
            ->method('loadFromId')
            ->with($this->callback(fn (GiftCardId $giftCardId) => $giftCardId->toString() === $id))
            ->willReturn($giftCard);

        $giftCard
            ->expects($this->once())
            ->method('adjustBalance')
            ->with(
                $this->callback(fn (Money $money) => $money->getAmount() === 500 && $money->getCurrency() === 'PLN'),
                'Bonus',
                $this->callback(fn (DateTimeImmutable $date) => $date->format('c') === $adjustedAt)
            );

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($giftCard);

        $handler = new AdjustBalance($provider, $persister);

        $handler->__invoke(new AdjustBalanceCommand(
            id: $id,
            amount: 500,
            currency: 'PLN',
            reason: 'Bonus',
            adjustedAt: $adjustedAt
        ));
    }
}
