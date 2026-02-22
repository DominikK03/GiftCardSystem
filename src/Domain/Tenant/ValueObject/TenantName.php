<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

use App\Domain\Tenant\Exception\InvalidTenantNameException;

final class TenantName
{
    public const string NAME_PATTERN = '/\A[a-z0-9](?:[a-z0-9 .-]{0,61}[a-z0-9.])?\z/i';
    private function __construct(
        private readonly string $value
    )
    {
    }

    public static function fromString(string $value): self
    {
        if (empty($value)) {
            throw InvalidTenantNameException::empty();
        }

        if (!preg_match(self::NAME_PATTERN, $value)){
            throw InvalidTenantNameException::invalidFormat($value);
        }

        return new self($value);
    }

    public function equals(TenantName $other): bool
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
