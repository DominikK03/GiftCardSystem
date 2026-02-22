<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use DomainException;

class InvalidExpirationDateException extends DomainException
{
    public static function create(): self
    {
        return new self("Expiration date must be in the future");
    }
}
