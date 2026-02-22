<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class TenantContextNotSetException extends TenantException
{
    public static function notSet(): self
    {
        return new self('Tenant context is not set. Authentication required.');
    }
}
