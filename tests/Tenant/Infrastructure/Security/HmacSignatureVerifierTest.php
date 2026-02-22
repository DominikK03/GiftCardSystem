<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Infrastructure\Security;

use App\Infrastructure\Tenant\Security\HmacSignatureBuilder;
use App\Infrastructure\Tenant\Security\HmacSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class HmacSignatureVerifierTest extends TestCase
{
    private HmacSignatureVerifier $verifier;
    private HmacSignatureBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new HmacSignatureBuilder();
        $this->verifier = new HmacSignatureVerifier($this->builder);
    }

    public function test_verifies_valid_signature(): void
    {
        $method = 'POST';
        $path = '/api/gift-cards';
        $timestamp = '1640000000';
        $nonce = 'abc123';
        $body = '{"amount":1000}';
        $secret = 'my-secret-aabbccddeeff00112233445566778899aabbccddeeff001122334455';

        $signatureString = $this->builder->buildSignatureString($method, $path, $timestamp, $nonce, $body);
        $expectedSignature = $this->builder->computeHmac($signatureString, $secret);

        $isValid = $this->verifier->verify(
            providedSignature: $expectedSignature,
            method: $method,
            path: $path,
            timestamp: $timestamp,
            nonce: $nonce,
            body: $body,
            secret: $secret
        );

        $this->assertTrue($isValid);
    }

    public function test_rejects_invalid_signature(): void
    {
        $isValid = $this->verifier->verify(
            providedSignature: 'invalid-signature-1234567890abcdef',
            method: 'POST',
            path: '/api/gift-cards',
            timestamp: '1640000000',
            nonce: 'abc123',
            body: '{"amount":1000}',
            secret: 'my-secret-aabbccddeeff00112233445566778899aabbccddeeff001122334455'
        );

        $this->assertFalse($isValid);
    }

    public function test_rejects_signature_with_tampered_body(): void
    {
        $method = 'POST';
        $path = '/api/gift-cards';
        $timestamp = '1640000000';
        $nonce = 'abc123';
        $originalBody = '{"amount":1000}';
        $tamperedBody = '{"amount":9999}';
        $secret = 'my-secret-aabbccddeeff00112233445566778899aabbccddeeff001122334455';

        $signatureString = $this->builder->buildSignatureString($method, $path, $timestamp, $nonce, $originalBody);
        $signature = $this->builder->computeHmac($signatureString, $secret);

        $isValid = $this->verifier->verify(
            providedSignature: $signature,
            method: $method,
            path: $path,
            timestamp: $timestamp,
            nonce: $nonce,
            body: $tamperedBody,
            secret: $secret
        );

        $this->assertFalse($isValid);
    }

    public function test_rejects_signature_with_wrong_secret(): void
    {
        $method = 'POST';
        $path = '/api/gift-cards';
        $timestamp = '1640000000';
        $nonce = 'abc123';
        $body = '{"amount":1000}';
        $correctSecret = 'correct-secret-aabbccddeeff00112233445566778899aabbccddeeff0011';
        $wrongSecret = 'wrong-secret-aabbccddeeff00112233445566778899aabbccddeeff001122';

        $signatureString = $this->builder->buildSignatureString($method, $path, $timestamp, $nonce, $body);
        $signature = $this->builder->computeHmac($signatureString, $correctSecret);

        $isValid = $this->verifier->verify(
            providedSignature: $signature,
            method: $method,
            path: $path,
            timestamp: $timestamp,
            nonce: $nonce,
            body: $body,
            secret: $wrongSecret
        );

        $this->assertFalse($isValid);
    }
}
