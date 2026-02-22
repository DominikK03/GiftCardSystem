<?php
declare(strict_types=1);
namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Command\RedeemCommand;
use App\Application\GiftCard\Handler\Redeem;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class RedeemTest extends TestCase
{

    public function test_it_redeems_and_persists(): void
    {
        $id = GiftCardId::generate()->toString();
        $provider = $this->createMock(GiftCardProviderInterface::class);
        $persister = $this->createMock(GiftCardPersisterInterface::class);

        $giftCard = $this->createMock(GiftCard::class);

        $provider
            ->expects($this->once())
            ->method('loadFromId')
            ->with($this->callback(fn (GiftCardId $id) => $id->toString() == $id))
            ->willReturn($giftCard);

        $giftCard
            ->expects($this->once())
            ->method('redeem')
            ->with($this->callback(fn (Money $money) => $money->getAmount() === 500 && $money->getCurrency() === 'PLN'));

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($giftCard);

        $handler = new Redeem($provider, $persister);

        $handler->__invoke(new RedeemCommand(
            giftCardId: $id,
            amount: 500,
            currency: 'PLN'
        ));
    }

}
