<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use App\Domain\User\Exception\InvalidUserRoleException;

enum UserRole: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case SUPPORT = 'SUPPORT';

    public static function fromString(string $role): self
    {
        return self::tryFrom(strtoupper($role))
            ?? throw InvalidUserRoleException::invalidRole($role);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function canManageUsers(): bool
    {
        return $this === self::OWNER;
    }

    public function canManageTenants(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }

    public function canManageGiftCards(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }

    public function canViewGiftCards(): bool
    {
        return true;
    }

    public function canViewTenants(): bool
    {
        return true;
    }
}
