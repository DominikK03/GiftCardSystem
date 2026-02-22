<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class InvalidUserRoleException extends UserException
{
    public static function invalidRole(string $role): self
    {
        return new self(sprintf(
            'Invalid user role: %s. Allowed roles: OWNER, ADMIN, SUPPORT',
            $role
        ));
    }
}
