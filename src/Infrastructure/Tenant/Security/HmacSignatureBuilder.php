<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Security;

final class HmacSignatureBuilder
{
    public function buildSignatureString(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $body
    ): string {
        $bodyHash = hash('sha256', $body);

        return implode("\n", [
            $method,
            $path,
            $timestamp,
            $nonce,
            $bodyHash
        ]);
    }

    public function computeHmac(string $signatureString, string $secret): string
    {
        return hash_hmac('sha256', $signatureString, $secret);
    }
}
