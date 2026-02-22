<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

use App\Domain\Tenant\Exception\InvalidPhoneNumberException;

final class PhoneNumber
{
    private const int MIN_LENGTH = 9;
    private const int MAX_LENGTH = 15;

    private function __construct(
        private readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = preg_replace('/[\s\-\(\)]/', '', trim($value));

        if (empty($normalized)) {
            throw InvalidPhoneNumberException::empty();
        }

        if (!preg_match('/^\+\d+$/', $normalized)) {
            throw InvalidPhoneNumberException::invalidFormat($value);
        }

        $length = strlen($normalized) - 1;
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw InvalidPhoneNumberException::invalidLength($value);
        }

        return new self($normalized);
    }

    public function equals(PhoneNumber $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
