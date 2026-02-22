<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class UserAlreadyExistsException extends UserException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('User with email %s already exists', $email));
    }
}
