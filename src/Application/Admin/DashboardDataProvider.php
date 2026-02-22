<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use App\Domain\GiftCard\Enum\GiftCardStatus;
use App\Domain\Tenant\Enum\DocumentType;
use App\Domain\Tenant\Port\TenantDocumentRepositoryInterface;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Domain\User\Port\UserRepository;

final class DashboardDataProvider
{
    public function __construct(
        private readonly GiftCardReadModelRepositoryInterface $giftCardRepository,
        private readonly TenantQueryRepositoryInterface $tenantQueryRepository,
        private readonly TenantDocumentRepositoryInterface $tenantDocumentRepository,
        private readonly UserRepository $userRepository,
    ) {}

    public function getStatistics(): array
    {
        $tenantNames = $this->buildTenantNameLookup();

        return [
            'tenants' => $this->getTenantStats(),
            'giftCards' => $this->getGiftCardStats(),
            'users' => $this->getUserStats(),
            'totalBalance' => $this->giftCardRepository->getTotalActiveBalance(),
            'totalRedeemed' => $this->giftCardRepository->getTotalRedeemed(),
            'invoicesThisMonth' => $this->getInvoicesThisMonth(),
            'expiringSoon' => $this->giftCardRepository->countExpiringSoon(
                new \DateTimeImmutable('+30 days')
            ),
            'recentActivity' => $this->giftCardRepository->findRecentActivity(10),
            'topTenants' => $this->getTopTenantsWithNames($tenantNames),
            'tenantNames' => $tenantNames,
        ];
    }

    private function getTenantStats(): array
    {
        $tenants = $this->tenantQueryRepository->findAll();
        $result = ['total' => count($tenants)];

        foreach ($tenants as $tenant) {
            $statusKey = $tenant->getStatus()->value;
            $result[$statusKey] = ($result[$statusKey] ?? 0) + 1;
        }

        return $result;
    }

    private function getGiftCardStats(): array
    {
        $result = ['total' => $this->giftCardRepository->countAll(null)];

        foreach (GiftCardStatus::cases() as $status) {
            $count = $this->giftCardRepository->countAll($status->value);
            if ($count > 0) {
                $result[$status->name] = $count;
            }
        }

        return $result;
    }

    private function getUserStats(): array
    {
        return [
            'total' => $this->userRepository->count(),
            'active' => $this->userRepository->countActive(),
            'inactive' => $this->userRepository->countInactive(),
        ];
    }

    private function getInvoicesThisMonth(): int
    {
        $firstDayOfMonth = new \DateTimeImmutable('first day of this month midnight');

        return $this->tenantDocumentRepository->countInvoicesSince($firstDayOfMonth);
    }

    /** @return array<string, string> */
    private function buildTenantNameLookup(): array
    {
        $tenants = $this->tenantQueryRepository->findAll();
        $lookup = [];

        foreach ($tenants as $tenant) {
            $lookup[$tenant->getId()->toString()] = (string) $tenant->getName();
        }

        return $lookup;
    }

    private function getTopTenantsWithNames(array $tenantNames): array
    {
        $topTenants = $this->giftCardRepository->getTopTenantsByActiveCards(5);

        return array_map(
            fn(array $row) => [
                'name' => $tenantNames[$row['tenantId']] ?? $row['tenantId'],
                'cardCount' => $row['cardCount'],
                'totalBalance' => $row['totalBalance'],
                'currency' => $row['currency'],
            ],
            $topTenants
        );
    }
}
