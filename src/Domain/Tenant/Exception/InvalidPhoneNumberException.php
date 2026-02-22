<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class InvalidPhoneNumberException extends TenantException
{
    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid phone number format: %s. Must start with + followed by digits', $value));
    }

    public static function invalidLength(string $value): self
    {
        return new self(sprintf('Phone number length is invalid: %s', $value));
    }

    public static function empty(): self
    {
        return new self('Phone number cannot be empty');
    }
}
