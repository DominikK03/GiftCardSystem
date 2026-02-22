<?php
declare(strict_types=1);
namespace App\Domain\Tenant\Exception;

class InvalidTenantIdException extends TenantException
{
    public static function invalidFormat(string $value): self
    {
        return new self(sprintf("Invalid ID format: %s", $value), 0);
    }

    public static function empty():self
    {
        return new self('ID cannot be empty', 0);
    }
}
