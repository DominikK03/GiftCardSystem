<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Infrastructure\Security;

use App\Infrastructure\Tenant\Security\HmacSignatureBuilder;
use PHPUnit\Framework\TestCase;

final class HmacSignatureBuilderTest extends TestCase
{
    private HmacSignatureBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new HmacSignatureBuilder();
    }

    public function test_builds_signature_string_from_request_data(): void
    {
        $signatureString = $this->builder->buildSignatureString(
            method: 'POST',
            path: '/api/gift-cards',
            timestamp: '1640000000',
            nonce: 'abc123',
            body: '{"amount":1000}'
        );

        $expected = "POST\n/api/gift-cards\n1640000000\nabc123\n" . hash('sha256', '{"amount":1000}');

        $this->assertEquals($expected, $signatureString);
    }

    public function test_handles_empty_body(): void
    {
        $signatureString = $this->builder->buildSignatureString(
            method: 'GET',
            path: '/api/gift-cards/123',
            timestamp: '1640000000',
            nonce: 'abc123',
            body: ''
        );

        $expected = "GET\n/api/gift-cards/123\n1640000000\nabc123\n" . hash('sha256', '');

        $this->assertEquals($expected, $signatureString);
    }

    public function test_computes_hmac_signature(): void
    {
        $signatureString = "POST\n/api/gift-cards\n1640000000\nabc123\nBODY_HASH";
        $secret = 'my-secret-key-64-chars-long-aabbccddeeff00112233445566778899';

        $signature = $this->builder->computeHmac($signatureString, $secret);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);
    }

    public function test_different_secrets_produce_different_signatures(): void
    {
        $signatureString = "POST\n/api/gift-cards\n1640000000\nabc123\nBODY_HASH";
        $secret1 = 'secret1-aabbccddeeff00112233445566778899aabbccddeeff0011223344';
        $secret2 = 'secret2-aabbccddeeff00112233445566778899aabbccddeeff0011223344';

        $signature1 = $this->builder->computeHmac($signatureString, $secret1);
        $signature2 = $this->builder->computeHmac($signatureString, $secret2);

        $this->assertNotEquals($signature1, $signature2);
    }

    public function test_same_input_produces_same_signature(): void
    {
        $signatureString = "POST\n/api/gift-cards\n1640000000\nabc123\nBODY_HASH";
        $secret = 'my-secret-aabbccddeeff00112233445566778899aabbccddeeff001122334455';

        $signature1 = $this->builder->computeHmac($signatureString, $secret);
        $signature2 = $this->builder->computeHmac($signatureString, $secret);

        $this->assertEquals($signature1, $signature2);
    }
}
