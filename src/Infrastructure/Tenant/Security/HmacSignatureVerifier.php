<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Security;

final class HmacSignatureVerifier
{
    public function __construct(
        private readonly HmacSignatureBuilder $builder
    ) {
    }

    public function verify(
        string $providedSignature,
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $body,
        string $secret
    ): bool {
        $signatureString = $this->builder->buildSignatureString(
            $method,
            $path,
            $timestamp,
            $nonce,
            $body
        );

        $expectedSignature = $this->builder->computeHmac($signatureString, $secret);

        return hash_equals($expectedSignature, $providedSignature);
    }
}
