<?php

declare(strict_types=1);

namespace App\Application\Tenant\Command;

final readonly class SuspendTenantCommand
{
    public function __construct(
        public string $tenantId
    ) {
    }
}
