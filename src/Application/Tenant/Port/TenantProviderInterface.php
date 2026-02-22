<?php

declare(strict_types=1);

namespace App\Application\Tenant\Port;

use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\ValueObject\TenantId;

interface TenantProviderInterface
{
    public function loadById(TenantId $id): Tenant;
}
