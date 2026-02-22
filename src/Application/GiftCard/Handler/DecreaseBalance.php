<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Command\DecreaseBalanceCommand;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DecreaseBalance
{
    public function __construct(
        private readonly GiftCardProviderInterface $provider,
        private readonly GiftCardPersisterInterface $persister
    ) {}

    public function __invoke(DecreaseBalanceCommand $command): void
    {
        $giftCard = $this->provider->loadFromId(GiftCardId::fromString($command->id));

        $giftCard->decreaseBalance(
            new Money($command->amount, $command->currency),
            $command->reason,
            new DateTimeImmutable($command->decreasedAt)
        );

        $this->persister->handle($giftCard);
    }
}
