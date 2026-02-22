<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class InvalidRepresentativeNameException extends TenantException
{
    public static function emptyFirstName(): self
    {
        return new self('Representative first name cannot be empty');
    }

    public static function emptyLastName(): self
    {
        return new self('Representative last name cannot be empty');
    }
}
