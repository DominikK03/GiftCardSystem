<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\ValueObject;

use App\Domain\GiftCard\Exception\InvalidGiftCardIdException;
use Ramsey\Uuid\Uuid;

final class GiftCardId
{
    private readonly string $value;

    public function __construct(string $value)
    {
        if(empty($value)){
            throw InvalidGiftCardIdException::empty();
        }
        if (!Uuid::isValid($value)){
            throw InvalidGiftCardIdException::invalidFormat($value);
        }
        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(GiftCardId $other): bool
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
