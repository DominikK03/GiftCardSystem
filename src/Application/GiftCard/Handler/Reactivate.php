<?php

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Command\ReactivateCommand;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class Reactivate
{
    public function __construct(
        private readonly GiftCardProviderInterface $provider,
        private readonly GiftCardPersisterInterface $persister
    )
    {
    }

    public function __invoke(ReactivateCommand $command): void
    {
        $giftCard = $this->provider->loadFromId(GiftCardId::fromString($command->id));

        $giftCard->reactivate(
            $command->reason,
            new \DateTimeImmutable($command->reactivatedAt)
        );

        $this->persister->handle($giftCard);
    }
}
