<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Infrastructure\Security;

use App\Domain\Tenant\Exception\TenantAuthenticationException;
use App\Domain\Tenant\ValueObject\ApiSecret;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Tenant\Security\HmacValidator;
use PHPUnit\Framework\TestCase;

class HmacValidatorTest extends TestCase
{
    private HmacValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new HmacValidator();
    }

    public function test_validates_correct_signature(): void
    {
        $tenantId = TenantId::generate();
        $timestamp = (string) time();
        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $apiSecret = ApiSecret::generate();

        $signature = $this->validator->calculateSignature(
            $tenantId,
            $timestamp,
            $requestBody,
            $apiSecret
        );

        $this->validator->validateSignature(
            $tenantId,
            $timestamp,
            $requestBody,
            $signature,
            $apiSecret
        );

        $this->assertTrue(true);
    }

    public function test_rejects_invalid_signature(): void
    {
        $this->expectException(TenantAuthenticationException::class);
        $this->expectExceptionMessage('Invalid HMAC signature');

        $tenantId = TenantId::generate();
        $timestamp = (string) time();
        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $apiSecret = ApiSecret::generate();

        $invalidSignature = 'invalid_signature_12345';

        $this->validator->validateSignature(
            $tenantId,
            $timestamp,
            $requestBody,
            $invalidSignature,
            $apiSecret
        );
    }

    public function test_rejects_expired_timestamp(): void
    {
        $this->expectException(TenantAuthenticationException::class);
        $this->expectExceptionMessage('Request timestamp expired');

        $tenantId = TenantId::generate();
        $expiredTimestamp = (string) (time() - 400);
        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $apiSecret = ApiSecret::generate();

        $signature = $this->validator->calculateSignature(
            $tenantId,
            $expiredTimestamp,
            $requestBody,
            $apiSecret
        );

        $this->validator->validateSignature(
            $tenantId,
            $expiredTimestamp,
            $requestBody,
            $signature,
            $apiSecret
        );
    }

    public function test_rejects_future_timestamp(): void
    {
        $this->expectException(TenantAuthenticationException::class);
        $this->expectExceptionMessage('Request timestamp expired');

        $tenantId = TenantId::generate();
        $futureTimestamp = (string) (time() + 400);
        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $apiSecret = ApiSecret::generate();

        $signature = $this->validator->calculateSignature(
            $tenantId,
            $futureTimestamp,
            $requestBody,
            $apiSecret
        );

        $this->validator->validateSignature(
            $tenantId,
            $futureTimestamp,
            $requestBody,
            $signature,
            $apiSecret
        );
    }

    public function test_rejects_invalid_timestamp_format(): void
    {
        $this->expectException(TenantAuthenticationException::class);
        $this->expectExceptionMessage('Invalid timestamp format');

        $tenantId = TenantId::generate();
        $invalidTimestamp = 'not_a_number';
        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $apiSecret = ApiSecret::generate();
        $signature = 'any_signature';

        $this->validator->validateSignature(
            $tenantId,
            $invalidTimestamp,
            $requestBody,
            $signature,
            $apiSecret
        );
    }

    public function test_signature_changes_with_different_tenant_id(): void
    {
        $tenantId1 = TenantId::generate();
        $tenantId2 = TenantId::generate();
        $timestamp = (string) time();
        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $apiSecret = ApiSecret::generate();

        $signature1 = $this->validator->calculateSignature($tenantId1, $timestamp, $requestBody, $apiSecret);
        $signature2 = $this->validator->calculateSignature($tenantId2, $timestamp, $requestBody, $apiSecret);

        $this->assertNotEquals($signature1, $signature2, 'Signatures should differ for different tenant IDs');
    }

    public function test_signature_changes_with_different_timestamp(): void
    {
        $tenantId = TenantId::generate();
        $timestamp1 = (string) time();
        $timestamp2 = (string) (time() + 1);
        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $apiSecret = ApiSecret::generate();

        $signature1 = $this->validator->calculateSignature($tenantId, $timestamp1, $requestBody, $apiSecret);
        $signature2 = $this->validator->calculateSignature($tenantId, $timestamp2, $requestBody, $apiSecret);

        $this->assertNotEquals($signature1, $signature2, 'Signatures should differ for different timestamps');
    }

    public function test_signature_changes_with_different_request_body(): void
    {
        $tenantId = TenantId::generate();
        $timestamp = (string) time();
        $requestBody1 = '{"amount":10000,"currency":"PLN"}';
        $requestBody2 = '{"amount":20000,"currency":"PLN"}';
        $apiSecret = ApiSecret::generate();

        $signature1 = $this->validator->calculateSignature($tenantId, $timestamp, $requestBody1, $apiSecret);
        $signature2 = $this->validator->calculateSignature($tenantId, $timestamp, $requestBody2, $apiSecret);

        $this->assertNotEquals($signature1, $signature2, 'Signatures should differ for different request bodies');
    }

    public function test_signature_is_deterministic(): void
    {
        $tenantId = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $timestamp = '1234567890';
        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $apiSecret = ApiSecret::fromString('test_secret_key_1234567890abcdefghijklmnopqrstuvwxyz12345678901234');

        $signature1 = $this->validator->calculateSignature($tenantId, $timestamp, $requestBody, $apiSecret);
        $signature2 = $this->validator->calculateSignature($tenantId, $timestamp, $requestBody, $apiSecret);

        $this->assertEquals($signature1, $signature2, 'Same inputs should always produce same signature');
    }
}
