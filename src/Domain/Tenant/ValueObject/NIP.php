<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

use App\Domain\Tenant\Exception\InvalidNIPException;

final class NIP
{
    private function __construct(
        private readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = str_replace('-', '', trim($value));

        if (empty($normalized)) {
            throw InvalidNIPException::empty();
        }

        if (strlen($normalized) !== 10) {
            throw InvalidNIPException::invalidLength($value);
        }

        if (!ctype_digit($normalized)) {
            throw InvalidNIPException::invalidFormat($value);
        }

        return new self($normalized);
    }

    public function equals(NIP $other): bool
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

    public function toFormattedString(): string
    {
        return substr($this->value, 0, 3) . '-' .
               substr($this->value, 3, 3) . '-' .
               substr($this->value, 6, 2) . '-' .
               substr($this->value, 8, 2);
    }
}
