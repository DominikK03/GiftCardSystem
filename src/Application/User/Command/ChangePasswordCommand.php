<?php

declare(strict_types=1);

namespace App\Application\User\Command;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public string $userId,
        public string $newPassword
    ) {}
}
