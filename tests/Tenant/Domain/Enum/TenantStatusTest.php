<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\Enum;

use App\Domain\Tenant\Enum\TenantStatus;
use PHPUnit\Framework\TestCase;

final class TenantStatusTest extends TestCase
{
    public function test_has_active_status(): void
    {
        $status = TenantStatus::ACTIVE;
        $this->assertEquals('ACTIVE', $status->name);
    }

    public function test_has_suspended_status(): void
    {
        $status = TenantStatus::SUSPENDED;
        $this->assertEquals('SUSPENDED', $status->name);
    }

    public function test_has_cancelled_status(): void
    {
        $status = TenantStatus::CANCELLED;
        $this->assertEquals('CANCELLED', $status->name);
    }

    public function test_can_get_all_statuses(): void
    {
        $statuses = TenantStatus::cases();
        $this->assertCount(3, $statuses);
    }
}
