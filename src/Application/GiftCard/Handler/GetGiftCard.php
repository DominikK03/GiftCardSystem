<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Query\GetGiftCardQuery;
use App\Application\GiftCard\View\GiftCardView;
use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetGiftCard
{
    public function __construct(
        private readonly GiftCardReadModelRepositoryInterface $repository
    ) {}

    public function __invoke(GetGiftCardQuery $query): ?GiftCardView
    {
        $readModel = $this->repository->findById($query->id);

        if (!$readModel) {
            return null;
        }

        return new GiftCardView(
            id: $readModel->id,
            balanceAmount: $readModel->balanceAmount,
            balanceCurrency: $readModel->balanceCurrency,
            initialAmount: $readModel->initialAmount,
            initialCurrency: $readModel->initialCurrency,
            status: $readModel->status,
            expiresAt: $readModel->expiresAt?->format('c'),
            createdAt: $readModel->createdAt->format('c'),
            activatedAt: $readModel->activatedAt?->format('c'),
            suspendedAt: $readModel->suspendedAt?->format('c'),
            cancelledAt: $readModel->cancelledAt?->format('c'),
            expiredAt: $readModel->expiredAt?->format('c'),
            depletedAt: $readModel->depletedAt?->format('c'),
            suspensionDuration: $readModel->suspensionDuration,
            updatedAt: $readModel->updatedAt->format('c')
        );
    }
}
