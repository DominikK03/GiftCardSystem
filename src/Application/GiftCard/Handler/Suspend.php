<?php

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Command\SuspendCommand;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class Suspend
{
    public function __construct(
        private readonly GiftCardProviderInterface $provider,
        private readonly GiftCardPersisterInterface $persister
    )
    {
    }

    public function __invoke(SuspendCommand $command): void
    {
        $giftCard = $this->provider->loadFromId(GiftCardId::fromString($command->id));

        $giftCard->suspend(
            $command->reason,
            $command->suspensionDurationSeconds,
            new \DateTimeImmutable($command->suspendedAt)
        );

        $this->persister->handle($giftCard);
    }
}
