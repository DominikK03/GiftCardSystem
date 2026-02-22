<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

use App\Domain\Tenant\Exception\InvalidTenantEmailException;

final class TenantEmail
{
    private function __construct(
        private readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (empty($normalized)) {
            throw InvalidTenantEmailException::empty();
        }

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw InvalidTenantEmailException::invalidFormat($value);
        }

        return new self($normalized);
    }

    public function equals(TenantEmail $other): bool
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
