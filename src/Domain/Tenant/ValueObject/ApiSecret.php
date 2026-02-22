<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

final class ApiSecret
{
    private function __construct(
        private readonly string $value
    ) {
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(ApiSecret $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
