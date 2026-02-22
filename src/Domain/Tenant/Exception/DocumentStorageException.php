<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class DocumentStorageException extends TenantException
{
    public static function failedToStore(string $path): self
    {
        return new self(sprintf('Failed to store document at path: %s', $path));
    }

    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('Document file not found at path: %s', $path));
    }

    public static function failedToDelete(string $path): self
    {
        return new self(sprintf('Failed to delete document at path: %s', $path));
    }
}
