<?php

declare(strict_types=1);

namespace App\Application\Tenant\Persister;

use App\Application\Tenant\Port\TenantPersisterInterface;
use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\Port\TenantRepositoryInterface;

final class TenantPersister implements TenantPersisterInterface
{
    public function __construct(
        private readonly TenantRepositoryInterface $repository
    ) {
    }

    public function handle(Tenant $tenant): void
    {
        $this->repository->save($tenant);
    }
}
