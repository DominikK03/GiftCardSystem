<?php

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Command\CreateCommand;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Tenant\TenantContext;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class Create
{
    public function __construct(
        private readonly GiftCardPersisterInterface $persister,
        private readonly TenantContext $tenantContext
    )
    {}

    /**
     * @throws \DateMalformedStringException
     */
    public function __invoke(CreateCommand $command): string
    {
        $giftCardId = GiftCardId::generate();

        if ($command->tenantId !== null && !$this->tenantContext->hasTenant()) {
            $this->tenantContext->setTenantId(TenantId::fromString($command->tenantId));
        }

        $tenantId = $command->tenantId ?? $this->tenantContext->getTenantId()->toString();

        $giftCard = GiftCard::create(
            $giftCardId,
            $tenantId,
            new Money($command->amount, $command->currency),
            null,
            $command->expiresAt ? new DateTimeImmutable($command->expiresAt) : null
        );

        $this->persister->handle($giftCard);

        return $giftCardId->toString();
    }
}
