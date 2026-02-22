<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Port;

use App\Domain\Tenant\Entity\Tenant;
interface TenantRepositoryInterface
{
    public function save(Tenant $tenant): void;
}
