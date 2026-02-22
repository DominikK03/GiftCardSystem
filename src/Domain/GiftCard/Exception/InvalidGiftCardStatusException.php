<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use App\Domain\GiftCard\Enum\GiftCardStatus;
use InvalidArgumentException;

class InvalidGiftCardStatusException extends InvalidArgumentException
{
    public static function invalid(GiftCardStatus $status): self
    {
        return new self(sprintf('Invalid gift card status: %s', $status->value));
    }
}
