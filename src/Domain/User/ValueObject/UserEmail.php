<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use App\Domain\User\Exception\InvalidUserEmailException;

final class UserEmail
{
    private function __construct(
        private readonly string $value
    ) {}

    public static function fromString(string $email): self
    {
        $email = trim($email);

        if (empty($email)) {
            throw InvalidUserEmailException::empty();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw InvalidUserEmailException::invalidFormat($email);
        }

        return new self(strtolower($email));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(UserEmail $other): bool
    {
        return $this->value === $other->value;
    }
}
