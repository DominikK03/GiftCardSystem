<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class TenantAuthenticationException extends TenantException
{
    public static function invalidApiKey(string $apiKey): self
    {
        return new self(sprintf('Invalid API key: %s', substr($apiKey, 0, 8) . '...'));
    }

    public static function tenantSuspended(): self
    {
        return new self('Tenant account is suspended');
    }

    public static function tenantCancelled(): self
    {
        return new self('Tenant account is cancelled');
    }

    public static function missingAuthenticationHeaders(): self
    {
        return new self('Missing required authentication headers (X-Tenant-Id, X-Timestamp, X-Signature)');
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid HMAC signature');
    }

    public static function expiredTimestamp(int $timestamp, int $maxAge): self
    {
        return new self(sprintf('Request timestamp expired. Timestamp: %d, Max age: %d seconds', $timestamp, $maxAge));
    }

    public static function invalidTimestamp(string $timestamp): self
    {
        return new self(sprintf('Invalid timestamp format: %s', $timestamp));
    }

    public static function tenantNotFound(string $tenantId): self
    {
        return new self(sprintf('Tenant not found: %s', $tenantId));
    }
}
