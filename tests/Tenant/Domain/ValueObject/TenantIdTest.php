<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Domain\Tenant\Exception\InvalidTenantIdException;
use App\Domain\Tenant\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

class TenantIdTest extends TestCase
{
    public function test_can_create_from_valid_uuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $tenantId = TenantId::fromString($uuid);
        $this->assertEquals($uuid, $tenantId->toString());
    }

    public function test_cannot_create_from_invalid_uuid(): void
    {
        $invalidUuid = 'invalid-uuid';
        $this->expectException(InvalidTenantIdException::class);
        TenantId::fromString($invalidUuid);
    }

    public function test_cannot_create_from_empty_string(): void
    {
        $emptyString = '';
        $this->expectException(InvalidTenantIdException::class);
        TenantId::fromString($emptyString);
    }

    public function test_generates_unique_ids_with_same_length(): void
    {
        $uuid1 = TenantId::generate()->toString();
        $uuid2 = TenantId::generate()->toString();

        self::assertEquals(strlen($uuid1), strlen($uuid2));
        self::assertNotSame($uuid1, $uuid2);
    }

}
