<?php

declare(strict_types=1);

namespace App\Tests\User\Domain\Entity;

use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserAlreadyDeactivatedException;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private const EMAIL = 'admin@example.com';
    private const PASSWORD_HASH = '$2y$13$hashedpassword';

    public function test_it_registers_user(): void
    {
        $userId = UserId::generate();
        $email = UserEmail::fromString(self::EMAIL);
        $role = UserRole::OWNER;

        $user = User::register($userId, $email, self::PASSWORD_HASH, $role);

        $this->assertTrue($user->getId()->equals($userId));
        $this->assertTrue($user->getEmail()->equals($email));
        $this->assertSame(self::PASSWORD_HASH, $user->getPasswordHash());
        $this->assertSame($role, $user->getRole());
        $this->assertTrue($user->isActive());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertNull($user->getDeactivatedAt());
    }

    public function test_it_changes_role(): void
    {
        $user = $this->createUser(UserRole::SUPPORT);

        $user->changeRole(UserRole::ADMIN);

        $this->assertSame(UserRole::ADMIN, $user->getRole());
    }

    public function test_it_deactivates_user(): void
    {
        $user = $this->createUser();

        $user->deactivate();

        $this->assertFalse($user->isActive());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getDeactivatedAt());
    }

    public function test_it_throws_exception_when_deactivating_already_deactivated_user(): void
    {
        $user = $this->createUser();
        $user->deactivate();

        $this->expectException(UserAlreadyDeactivatedException::class);

        $user->deactivate();
    }

    public function test_it_activates_deactivated_user(): void
    {
        $user = $this->createUser();
        $user->deactivate();

        $user->activate();

        $this->assertTrue($user->isActive());
        $this->assertNull($user->getDeactivatedAt());
    }

    public function test_it_changes_password(): void
    {
        $user = $this->createUser();
        $newPasswordHash = '$2y$13$newhashedpassword';

        $user->changePassword($newPasswordHash);

        $this->assertSame($newPasswordHash, $user->getPasswordHash());
    }

    public function test_owner_can_manage_users(): void
    {
        $user = $this->createUser(UserRole::OWNER);

        $this->assertTrue($user->canManageUsers());
        $this->assertTrue($user->canManageTenants());
        $this->assertTrue($user->canManageGiftCards());
    }

    public function test_admin_can_manage_tenants_and_gift_cards_but_not_users(): void
    {
        $user = $this->createUser(UserRole::ADMIN);

        $this->assertFalse($user->canManageUsers());
        $this->assertTrue($user->canManageTenants());
        $this->assertTrue($user->canManageGiftCards());
    }

    public function test_support_can_only_view(): void
    {
        $user = $this->createUser(UserRole::SUPPORT);

        $this->assertFalse($user->canManageUsers());
        $this->assertFalse($user->canManageTenants());
        $this->assertFalse($user->canManageGiftCards());
    }

    private function createUser(UserRole $role = UserRole::OWNER): User
    {
        return User::register(
            UserId::generate(),
            UserEmail::fromString(self::EMAIL),
            self::PASSWORD_HASH,
            $role
        );
    }
}
