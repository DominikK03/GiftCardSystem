<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class InvalidUserEmailException extends UserException
{
    public static function empty(): self
    {
        return new self('User email cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid email format: %s', $value));
    }
}
