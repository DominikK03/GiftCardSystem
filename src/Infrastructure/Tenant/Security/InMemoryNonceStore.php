<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Security;
final class InMemoryNonceStore implements NonceStoreInterface
{
    /** @var array<string, int> Map of nonce => expiration timestamp */
    private array $nonces = [];

    public function hasNonce(string $nonce): bool
    {
        $this->cleanExpired();

        return isset($this->nonces[$nonce]);
    }

    public function storeNonce(string $nonce, int $ttl): void
    {
        $this->cleanExpired();

        $this->nonces[$nonce] = time() + $ttl;
    }

    private function cleanExpired(): void
    {
        $now = time();

        foreach ($this->nonces as $nonce => $expiresAt) {
            if ($expiresAt < $now) {
                unset($this->nonces[$nonce]);
            }
        }
    }
}
