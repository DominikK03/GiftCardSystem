<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class InvalidUserIdException extends UserException
{
    public static function empty(): self
    {
        return new self('User ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid User ID format: %s', $value));
    }
}
