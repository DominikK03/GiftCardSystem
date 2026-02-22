<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\User\ValueObject\UserRole;

final class InsufficientPermissionsException extends UserException
{
    public static function forAction(string $action, UserRole $currentRole): self
    {
        return new self(sprintf(
            'Insufficient permissions to perform action: %s. Current role: %s',
            $action,
            $currentRole->toString()
        ));
    }

    public static function onlyOwnerCanManageUsers(): self
    {
        return new self('Only users with OWNER role can manage other users');
    }
}
