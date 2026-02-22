<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\ValueObject;

use App\Domain\GiftCard\Exception\InvalidCustomerEmailException;

final class CustomerEmail
{
    private function __construct(
        private readonly string $value
    ) {}

    public static function fromString(string $email): self
    {
        $email = trim($email);

        if (empty($email)) {
            throw InvalidCustomerEmailException::empty();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw InvalidCustomerEmailException::invalidFormat($email);
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

    public function equals(CustomerEmail $other): bool
    {
        return $this->value === $other->value;
    }
}
