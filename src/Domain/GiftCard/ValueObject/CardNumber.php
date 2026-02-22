<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\ValueObject;

use App\Domain\GiftCard\Exception\InvalidCardNumberException;

final class CardNumber
{
    private const PATTERN = '/^[A-Z0-9]{12}$/';
    private const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private function __construct(
        private readonly string $value
    ) {}

    public static function generate(): self
    {
        $number = '';
        for ($i = 0; $i < 12; $i++) {
            $number .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }

        return new self($number);
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if (empty($value)) {
            throw InvalidCardNumberException::empty();
        }

        $value = strtoupper($value);

        if (!preg_match(self::PATTERN, $value)) {
            throw InvalidCardNumberException::invalidFormat($value);
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

    public function equals(CardNumber $other): bool
    {
        return $this->value === $other->value;
    }
}
