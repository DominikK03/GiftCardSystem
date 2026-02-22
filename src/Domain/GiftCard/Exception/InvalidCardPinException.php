<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use InvalidArgumentException;

class InvalidCardPinException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('Card PIN cannot be empty', 0);
    }

    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid card PIN format: %s. Expected 8 digits.', $value), 0);
    }
}
