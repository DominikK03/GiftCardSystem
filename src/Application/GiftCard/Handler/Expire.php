<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Command\ExpireCommand;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class Expire
{
    public function __construct(
        private readonly GiftCardProviderInterface $provider,
        private readonly GiftCardPersisterInterface $persister
    ) {}

    public function __invoke(ExpireCommand $command): void
    {
        $giftCard = $this->provider->loadFromIdAsSystem(GiftCardId::fromString($command->id));
        $giftCard->expire(new DateTimeImmutable($command->expiredAt));

        $this->persister->handle($giftCard);
    }
}
