<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Provider;

use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\Exception\GiftCardNotFoundException;
use App\Domain\GiftCard\Port\GiftCardRepository;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Application\GiftCard\Port\GiftCardProviderInterface;
use App\Domain\Tenant\Exception\TenantMismatchException;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Tenant\TenantContext;

final class GiftCardProvider implements GiftCardProviderInterface
{
    public function __construct(
        private readonly GiftCardRepository $repository,
        private readonly TenantContext $tenantContext
    ) {}

    public function loadFromId(GiftCardId $id): GiftCard
    {
        $giftCard = $this->repository->load($id);
        if ($giftCard === null) {
            throw GiftCardNotFoundException::forId($id);
        }

        $currentTenantId = $this->tenantContext->getTenantId();
        $giftCardTenantId = TenantId::fromString($giftCard->getTenantId());

        if (!$currentTenantId->equals($giftCardTenantId)) {
            throw TenantMismatchException::accessDenied($currentTenantId, $giftCardTenantId);
        }

        return $giftCard;
    }

    public function loadFromIdAsSystem(GiftCardId $id): GiftCard
    {
        $giftCard = $this->repository->load($id);
        if ($giftCard === null) {
            throw GiftCardNotFoundException::forId($id);
        }

        return $giftCard;
    }
}
