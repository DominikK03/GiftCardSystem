<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use InvalidArgumentException;

class InvalidCustomerEmailException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('Customer email cannot be empty', 0);
    }

    public static function invalidFormat(string $email): self
    {
        return new self(sprintf('Invalid customer email format: %s', $email), 0);
    }
}
