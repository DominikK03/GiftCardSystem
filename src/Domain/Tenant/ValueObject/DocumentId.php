<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

use App\Domain\Tenant\Exception\InvalidDocumentIdException;
use Ramsey\Uuid\Uuid;

final class DocumentId
{
    private function __construct(
        private readonly string $value
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        if (empty($value)) {
            throw InvalidDocumentIdException::empty();
        }

        if (!Uuid::isValid($value)) {
            throw InvalidDocumentIdException::invalidFormat($value);
        }

        return new self($value);
    }

    public function equals(DocumentId $other): bool
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
