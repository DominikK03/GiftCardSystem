<?php

declare(strict_types=1);

namespace App\Application\Tenant\Provider;

use App\Application\Tenant\Port\TenantProviderInterface;
use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Domain\Tenant\ValueObject\TenantId;

final class TenantProvider implements TenantProviderInterface
{
    public function __construct(
        private readonly TenantQueryRepositoryInterface $repository
    ) {
    }

    public function loadById(TenantId $id): Tenant
    {
        return $this->repository->findById($id);
    }
}
