<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\User\ValueObject\UserId;

final class UserNotFoundException extends UserException
{
    public static function forId(UserId $id): self
    {
        return new self(sprintf('User not found with ID: %s', $id->toString()));
    }

    public static function forEmail(string $email): self
    {
        return new self(sprintf('User not found with email: %s', $email));
    }
}
