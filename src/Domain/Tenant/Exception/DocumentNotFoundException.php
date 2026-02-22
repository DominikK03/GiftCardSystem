<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class DocumentNotFoundException extends TenantException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Document with ID "%s" not found', $id));
    }
}
