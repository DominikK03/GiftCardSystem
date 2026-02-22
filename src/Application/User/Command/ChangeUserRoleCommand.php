<?php

declare(strict_types=1);

namespace App\Application\User\Command;

final readonly class ChangeUserRoleCommand
{
    public function __construct(
        public string $userId,
        public string $newRole
    ) {}
}
