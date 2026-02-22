<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use DateTimeImmutable;
use DomainException;

class GiftCardNotExpiredException extends DomainException
{
    public static function create(DateTimeImmutable $expiresAt)
    {
        return new self(sprintf('Gift Card is not expired yet. Expires at: %s', $expiresAt->format('Y-m-d H:i:s')));

    }

}
