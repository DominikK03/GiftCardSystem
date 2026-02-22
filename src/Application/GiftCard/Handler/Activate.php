<?php

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Command\ActivateCommand;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class Activate
{
    public function __construct(
        private readonly GiftCardProviderInterface $provider,
        private readonly GiftCardPersisterInterface $persister
    )
    {
    }

    public function __invoke(ActivateCommand $command): void
    {
        $giftCard = $this->provider->loadFromId(GiftCardId::fromString($command->id));

        $giftCard->activate(new \DateTimeImmutable($command->activatedAt));

        $this->persister->handle($giftCard);
    }

}
