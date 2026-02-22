<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class InvalidAddressException extends TenantException
{
    public static function emptyField(string $fieldName): self
    {
        return new self(sprintf('Address %s cannot be empty', $fieldName));
    }
}
