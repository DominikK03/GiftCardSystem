<?php

namespace App\Domain\Tenant\Exception;

use App\Domain\Tenant\Exception\TenantException;

class InvalidTenantNameException extends TenantException
{
    public static function invalidFormat(string $value): self
    {
        return new self(sprintf("Invalid Name format: %s", $value), 0);
    }

    public static function empty():self
    {
        return new self('Name cannot be empty', 0);
    }
}
