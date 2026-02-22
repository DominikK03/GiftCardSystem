<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Port;

use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\Exception\TenantNotFoundException;
use App\Domain\Tenant\ValueObject\TenantEmail;
use App\Domain\Tenant\ValueObject\TenantId;
interface TenantQueryRepositoryInterface
{
    /**
     * @throws TenantNotFoundException
     */
    public function findById(TenantId $id): Tenant;

    public function findByEmail(TenantEmail $email): ?Tenant;

    public function existsByEmail(TenantEmail $email): bool;

    /**
     * @return Tenant[]
     */
    public function findAll(): array;

    public function findByApiKey(string $apiKey): ?Tenant;
}
