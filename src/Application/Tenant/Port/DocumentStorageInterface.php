<?php

declare(strict_types=1);

namespace App\Application\Tenant\Port;

interface DocumentStorageInterface
{
    public function store(string $content, string $directory, string $filename): string;
    public function read(string $storagePath): string;
    public function delete(string $storagePath): void;
}
