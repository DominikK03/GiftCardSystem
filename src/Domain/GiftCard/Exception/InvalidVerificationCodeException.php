<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use InvalidArgumentException;

class InvalidVerificationCodeException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('Verification code cannot be empty', 0);
    }

    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid verification code format: %s. Expected 6 digits.', $value), 0);
    }
}
