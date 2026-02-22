<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

use App\Domain\Tenant\ValueObject\TenantId;

class TenantMismatchException extends TenantException
{
    public static function accessDenied(TenantId $requestedTenantId, TenantId $actualTenantId): self
    {
        return new self(sprintf(
            'Tenant mismatch. Current tenant "%s" cannot access resources of tenant "%s"',
            $requestedTenantId->toString(),
            $actualTenantId->toString()
        ));
    }

    public static function eventWithoutTenant(string $aggregateId): self
    {
        return new self(sprintf(
            'Event stream for aggregate "%s" contains events without tenant_id in metadata',
            $aggregateId
        ));
    }
}
