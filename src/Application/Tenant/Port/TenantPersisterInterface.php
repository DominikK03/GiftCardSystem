<?php

declare(strict_types=1);

namespace App\Application\Tenant\Port;

use App\Domain\Tenant\Entity\Tenant;

interface TenantPersisterInterface
{
    public function handle(Tenant $tenant): void;
}
