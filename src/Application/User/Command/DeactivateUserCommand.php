<?php

declare(strict_types=1);

namespace App\Application\User\Command;

final readonly class DeactivateUserCommand
{
    public function __construct(
        public string $userId
    ) {}
}
