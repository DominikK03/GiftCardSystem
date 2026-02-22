<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class InvalidNIPException extends TenantException
{
    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid NIP format: %s', $value));
    }

    public static function invalidLength(string $value): self
    {
        return new self(sprintf('NIP must have exactly 10 digits, got: %s', $value));
    }

    public static function empty(): self
    {
        return new self('NIP cannot be empty');
    }
}
