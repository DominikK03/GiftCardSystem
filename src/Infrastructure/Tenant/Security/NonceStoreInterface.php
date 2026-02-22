<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Security;

interface NonceStoreInterface
{
    public function hasNonce(string $nonce): bool;
    public function storeNonce(string $nonce, int $ttl): void;
}
