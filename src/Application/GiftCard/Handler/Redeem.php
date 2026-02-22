<?php

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Command\RedeemCommand;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async')]
final class Redeem
{
    public function __construct(
        private readonly GiftCardProviderInterface $provider,
        private readonly GiftCardPersisterInterface $persister
    ) {}

    public function __invoke(RedeemCommand $command): void
    {
        $giftCard = $this->provider->loadFromId(GiftCardId::fromString($command->giftCardId));

        $giftCard->redeem(new Money($command->amount, $command->currency));

        $this->persister->handle($giftCard);
    }
}
