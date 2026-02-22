<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

enum TenantStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case CANCELLED = 'CANCELLED';
}
