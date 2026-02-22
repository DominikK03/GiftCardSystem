<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\ValueObject;

use App\Domain\GiftCard\Exception\InvalidVerificationCodeException;

final class VerificationCode
{
    private const PATTERN = '/^[0-9]{6}$/';

    private function __construct(
        private readonly string $value
    ) {}

    public static function generate(): self
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return new self($code);
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if (empty($value)) {
            throw InvalidVerificationCodeException::empty();
        }

        if (!preg_match(self::PATTERN, $value)) {
            throw InvalidVerificationCodeException::invalidFormat($value);
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(VerificationCode $other): bool
    {
        return $this->value === $other->value;
    }
}
