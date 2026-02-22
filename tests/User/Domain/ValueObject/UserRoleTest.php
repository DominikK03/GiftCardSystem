<?php

declare(strict_types=1);

namespace App\Tests\User\Domain\ValueObject;

use App\Domain\User\Exception\InvalidUserRoleException;
use App\Domain\User\ValueObject\UserRole;
use PHPUnit\Framework\TestCase;

final class UserRoleTest extends TestCase
{
    public function test_it_creates_role_from_valid_string(): void
    {
        $role = UserRole::fromString('OWNER');

        $this->assertSame(UserRole::OWNER, $role);
        $this->assertSame('OWNER', $role->toString());
    }

    public function test_it_handles_case_insensitive_input(): void
    {
        $role = UserRole::fromString('owner');

        $this->assertSame(UserRole::OWNER, $role);
    }

    public function test_it_throws_exception_for_invalid_role(): void
    {
        $this->expectException(InvalidUserRoleException::class);
        $this->expectExceptionMessage('Invalid user role: INVALID');

        UserRole::fromString('INVALID');
    }

    public function test_owner_permissions(): void
    {
        $role = UserRole::OWNER;

        $this->assertTrue($role->canManageUsers());
        $this->assertTrue($role->canManageTenants());
        $this->assertTrue($role->canManageGiftCards());
        $this->assertTrue($role->canViewGiftCards());
        $this->assertTrue($role->canViewTenants());
    }

    public function test_admin_permissions(): void
    {
        $role = UserRole::ADMIN;

        $this->assertFalse($role->canManageUsers());
        $this->assertTrue($role->canManageTenants());
        $this->assertTrue($role->canManageGiftCards());
        $this->assertTrue($role->canViewGiftCards());
        $this->assertTrue($role->canViewTenants());
    }

    public function test_support_permissions(): void
    {
        $role = UserRole::SUPPORT;

        $this->assertFalse($role->canManageUsers());
        $this->assertFalse($role->canManageTenants());
        $this->assertFalse($role->canManageGiftCards());
        $this->assertTrue($role->canViewGiftCards());
        $this->assertTrue($role->canViewTenants());
    }
}
