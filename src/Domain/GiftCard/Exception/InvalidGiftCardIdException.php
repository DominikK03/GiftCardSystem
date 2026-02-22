<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;
use InvalidArgumentException;

class InvalidGiftCardIdException extends InvalidArgumentException
{
    public static function empty():self
    {
        return new self('Gift card ID cannot be empty', 0);
    }

    public static function invalidFormat(string $value): self
    {
        return new self(sprintf("Invalid gift card ID format: %s", $value), 0);
    }
}
