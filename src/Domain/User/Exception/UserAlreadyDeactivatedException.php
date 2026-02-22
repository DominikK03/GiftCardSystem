<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class UserAlreadyDeactivatedException extends UserException
{
    public static function create(): self
    {
        return new self('User is already deactivated');
    }
}
