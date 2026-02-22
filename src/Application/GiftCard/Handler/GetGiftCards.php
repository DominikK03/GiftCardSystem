<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use App\Application\GiftCard\Query\GetGiftCardsQuery;
use App\Application\GiftCard\View\GiftCardView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetGiftCards
{
    public function __construct(
        private readonly GiftCardReadModelRepositoryInterface $repository
    ) {}

    public function __invoke(GetGiftCardsQuery $query): array
    {
        $readModels = $this->repository->findByTenant(
            $query->tenantId,
            $query->page,
            $query->limit,
            $query->status
        );

        $total = $this->repository->countByTenant($query->tenantId, $query->status);

        $giftCards = array_map(
            fn($readModel) => new GiftCardView(
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
            ),
            $readModels
        );

        return [
            'giftCards' => $giftCards,
            'total' => $total,
            'page' => $query->page,
            'limit' => $query->limit,
            'totalPages' => (int) ceil($total / $query->limit),
        ];
    }
}
