<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Domain\Tenant\Exception\InvalidTenantNameException;
use App\Domain\Tenant\ValueObject\TenantName;
use PHPUnit\Framework\TestCase;

final class TenantNameTest extends TestCase
{
    public function test_can_create_from_valid_string(): void
    {
        $stringName = "Test sp. z.o.o";
        $tenantName = TenantName::fromString($stringName);
        $this->assertEquals($stringName, $tenantName->toString());
    }

    public function test_cannot_create_from_invalid_string(): void
    {
        $stringName = '1234!@#';
        $this->expectException(InvalidTenantNameException::class);
        TenantName::fromString($stringName);
    }

    public function test_cannot_create_from_empty_string(): void
    {
        $stringName = '';
        $this->expectException(InvalidTenantNameException::class);
        TenantName::fromString($stringName);
    }

    public function test_can_create_with_dot_at_end(): void
    {
        $stringName = 'Test Company Ltd.';
        $tenantName = TenantName::fromString($stringName);
        $this->assertEquals($stringName, $tenantName->toString());
    }

    public function test_two_names_with_same_value_are_equal(): void
    {
        $name1 = TenantName::fromString('Test Company');
        $name2 = TenantName::fromString('Test Company');

        $this->assertTrue($name1->equals($name2));
    }

    public function test_two_names_with_different_values_are_not_equal(): void
    {
        $name1 = TenantName::fromString('Company A');
        $name2 = TenantName::fromString('Company B');

        $this->assertFalse($name1->equals($name2));
    }
}
