<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\ValueObject;

use App\Domain\GiftCard\Exception\InvalidCardPinException;

final class CardPin
{
    private const PATTERN = '/^[0-9]{8}$/';

    private function __construct(
        private readonly string $value
    ) {}

    public static function generate(): self
    {
        $pin = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        return new self($pin);
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if (empty($value)) {
            throw InvalidCardPinException::empty();
        }

        if (!preg_match(self::PATTERN, $value)) {
            throw InvalidCardPinException::invalidFormat($value);
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

    public function equals(CardPin $other): bool
    {
        return $this->value === $other->value;
    }
}
