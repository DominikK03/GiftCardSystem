<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use DomainException;

final class NoExpirationDateException extends DomainException
{
    public static function create(): self
    {
        return new self('This gift card has no expiration date set');
    }
}
