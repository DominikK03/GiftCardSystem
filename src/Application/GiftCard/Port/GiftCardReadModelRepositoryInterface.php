<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Port;

use App\Application\GiftCard\ReadModel\GiftCardReadModel;

interface GiftCardReadModelRepositoryInterface
{
    public function findById(string $id): ?GiftCardReadModel;

    public function findByCardNumber(string $cardNumber): ?GiftCardReadModel;

    /**
     * @return GiftCardReadModel[]
     */
    public function findByStatus(string $status): array;

    /**
     * @return GiftCardReadModel[]
     */
    public function findExpiring(\DateTimeImmutable $before): array;

    /**
     * @return GiftCardReadModel[]
     */
    public function findByTenant(string $tenantId, int $page, int $limit, ?string $status): array;

    public function countByTenant(string $tenantId, ?string $status): int;

    /**
     * @return GiftCardReadModel[]
     */
    public function findAllPaginated(int $page, int $limit, ?string $status, ?string $tenantId = null, ?string $id = null): array;

    public function countAll(?string $status, ?string $tenantId = null, ?string $id = null): int;

    /** @return array<array{total: int, currency: string}> */
    public function getTotalActiveBalance(): array;

    /** @return array<array{total: int, currency: string}> */
    public function getTotalRedeemed(): array;

    public function countExpiringSoon(\DateTimeImmutable $threshold): int;

    /** @return GiftCardReadModel[] */
    public function findRecentActivity(int $limit): array;

    /** @return array<array{tenantId: string, cardCount: int, totalBalance: int, currency: string}> */
    public function getTopTenantsByActiveCards(int $limit): array;
}
