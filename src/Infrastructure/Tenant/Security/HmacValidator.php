<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Security;

use App\Domain\Tenant\Exception\TenantAuthenticationException;
use App\Domain\Tenant\ValueObject\ApiSecret;
use App\Domain\Tenant\ValueObject\TenantId;
class HmacValidator
{
    private const MAX_TIMESTAMP_AGE_SECONDS = 300;

    public function validateSignature(
        TenantId $tenantId,
        string $timestamp,
        string $requestBody,
        string $providedSignature,
        ApiSecret $apiSecret
    ): void {
        if (!is_numeric($timestamp)) {
            throw TenantAuthenticationException::invalidTimestamp($timestamp);
        }

        $timestampInt = (int) $timestamp;

        $currentTimestamp = time();
        $age = abs($currentTimestamp - $timestampInt);

        if ($age > self::MAX_TIMESTAMP_AGE_SECONDS) {
            throw TenantAuthenticationException::expiredTimestamp($timestampInt, self::MAX_TIMESTAMP_AGE_SECONDS);
        }

        $expectedSignature = $this->calculateSignature(
            $tenantId,
            $timestamp,
            $requestBody,
            $apiSecret
        );

        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw TenantAuthenticationException::invalidSignature();
        }
    }

    public function calculateSignature(
        TenantId $tenantId,
        string $timestamp,
        string $requestBody,
        ApiSecret $apiSecret
    ): string {
        $data = $tenantId->toString() . $timestamp . $requestBody;

        return hash_hmac('sha256', $data, $apiSecret->toString());
    }
}
