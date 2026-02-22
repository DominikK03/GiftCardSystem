<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

use App\Domain\Tenant\Exception\InvalidTenantIdException;
use Ramsey\Uuid\Uuid;

final class TenantId
{
    private function __construct(
        private readonly string $value
    )
    {
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        if (empty($value)) {
            throw InvalidTenantIdException::empty();
        }

        if (!Uuid::isValid($value)) {
            throw InvalidTenantIdException::invalidFormat($value);
        }

        return new self($value);
    }

    public function equals(TenantId $other): bool
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
