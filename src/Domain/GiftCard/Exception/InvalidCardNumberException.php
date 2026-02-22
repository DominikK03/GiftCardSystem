<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use InvalidArgumentException;

class InvalidCardNumberException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('Card number cannot be empty', 0);
    }

    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid card number format: %s. Expected 12 alphanumeric uppercase characters.', $value), 0);
    }
}
