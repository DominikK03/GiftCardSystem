<?php

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Command\AdjustBalanceCommand;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AdjustBalance
{
    public function __construct(
        private readonly GiftCardProviderInterface $provider,
        private readonly GiftCardPersisterInterface $persister
    )
    {
    }

    public function __invoke(AdjustBalanceCommand $command): void
    {
        $giftCard = $this->provider->loadFromId(GiftCardId::fromString($command->id));

        $giftCard->adjustBalance(
            Money::fromPrimitives($command->amount, $command->currency),
            $command->reason,
            new \DateTimeImmutable($command->adjustedAt)
        );

        $this->persister->handle($giftCard);
    }
}
