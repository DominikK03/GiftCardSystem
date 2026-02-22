<?php

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Command\CancelCommand;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class Cancel
{
    public function __construct(
        private readonly GiftCardProviderInterface $provider,
        private readonly GiftCardPersisterInterface $persister
    )
    {
    }

    public function __invoke(CancelCommand $command): void
    {
        $giftCard = $this->provider->loadFromId(GiftCardId::fromString($command->id));

        $giftCard->cancel(
            $command->reason,
            new \DateTimeImmutable($command->cancelledAt)
        );

        $this->persister->handle($giftCard);
    }
}
