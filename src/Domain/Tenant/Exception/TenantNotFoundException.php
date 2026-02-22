<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

use App\Domain\Tenant\ValueObject\TenantId;

class TenantNotFoundException extends TenantException
{
    public static function withId(TenantId $id): self
    {
        return new self(sprintf('Tenant with ID "%s" not found', $id->toString()));
    }
}
