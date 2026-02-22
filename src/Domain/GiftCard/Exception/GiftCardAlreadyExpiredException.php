<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use DateTimeImmutable;
use DomainException;

final class GiftCardAlreadyExpiredException extends DomainException
{
    public static function cannotActivate(DateTimeImmutable $expiresAt): self
    {
        return new self(
            sprintf(
                'Cannot activate gift card that has already expired (expiration date: %s)',
                $expiresAt->format('Y-m-d H:i:s')
            )
        );
    }

    public static function cannotRedeem(DateTimeImmutable $expiresAt): self
    {
        return new self(
            sprintf(
                'Cannot redeem gift card that has already expired (expiration date: %s)',
                $expiresAt->format('Y-m-d H:i:s')
            )
        );
    }
}
