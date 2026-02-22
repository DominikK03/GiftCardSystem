<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class InvalidTenantEmailException extends TenantException
{
    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid email format: %s', $value));
    }

    public static function empty(): self
    {
        return new self('Email cannot be empty');
    }
}
