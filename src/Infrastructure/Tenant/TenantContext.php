<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Exception\TenantContextNotSetException;
use App\Domain\Tenant\ValueObject\TenantId;
class TenantContext
{
    private ?TenantId $tenantId = null;

    public function setTenantId(TenantId $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): TenantId
    {
        if ($this->tenantId === null) {
            throw TenantContextNotSetException::notSet();
        }

        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
