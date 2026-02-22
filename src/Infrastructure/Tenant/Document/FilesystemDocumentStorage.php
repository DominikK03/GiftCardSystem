<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Document;

use App\Application\Tenant\Port\DocumentStorageInterface;
use App\Domain\Tenant\Exception\DocumentStorageException;

final class FilesystemDocumentStorage implements DocumentStorageInterface
{
    public function __construct(
        private readonly string $storageBasePath
    ) {
    }

    public function store(string $content, string $directory, string $filename): string
    {
        $fullDirectory = $this->storageBasePath . '/' . $directory;

        if (!is_dir($fullDirectory)) {
            if (!mkdir($fullDirectory, 0755, true) && !is_dir($fullDirectory)) {
                throw DocumentStorageException::failedToStore($fullDirectory);
            }
        }

        $fullPath = $fullDirectory . '/' . $filename;
        $relativePath = $directory . '/' . $filename;

        if (file_put_contents($fullPath, $content) === false) {
            throw DocumentStorageException::failedToStore($relativePath);
        }

        return $relativePath;
    }

    public function read(string $storagePath): string
    {
        $fullPath = $this->storageBasePath . '/' . $storagePath;

        if (!file_exists($fullPath)) {
            throw DocumentStorageException::fileNotFound($storagePath);
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw DocumentStorageException::fileNotFound($storagePath);
        }

        return $content;
    }

    public function delete(string $storagePath): void
    {
        $fullPath = $this->storageBasePath . '/' . $storagePath;

        if (file_exists($fullPath) && !unlink($fullPath)) {
            throw DocumentStorageException::failedToDelete($storagePath);
        }
    }
}
