<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class InvalidDocumentIdException extends TenantException
{
    public static function empty(): self
    {
        return new self('Document ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid Document ID format: %s', $value));
    }
}
