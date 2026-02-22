<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Infrastructure;

use App\Domain\Tenant\Exception\TenantContextNotSetException;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Tenant\TenantContext;
use PHPUnit\Framework\TestCase;

final class TenantContextTest extends TestCase
{
    public function test_can_set_and_get_tenant_id(): void
    {
        $context = new TenantContext();
        $tenantId = TenantId::generate();

        $context->setTenantId($tenantId);

        $this->assertEquals($tenantId, $context->getTenantId());
    }

    public function test_throws_exception_when_tenant_not_set(): void
    {
        $context = new TenantContext();

        $this->expectException(TenantContextNotSetException::class);
        $context->getTenantId();
    }

    public function test_can_check_if_tenant_is_set(): void
    {
        $context = new TenantContext();

        $this->assertFalse($context->hasTenant());

        $context->setTenantId(TenantId::generate());

        $this->assertTrue($context->hasTenant());
    }

    public function test_can_clear_tenant(): void
    {
        $context = new TenantContext();
        $context->setTenantId(TenantId::generate());

        $context->clear();

        $this->assertFalse($context->hasTenant());
    }
}
