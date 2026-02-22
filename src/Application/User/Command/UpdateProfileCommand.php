<?php

declare(strict_types=1);

namespace App\Application\User\Command;

final readonly class UpdateProfileCommand
{
    public function __construct(
        public string $userId,
        public ?string $firstName,
        public ?string $lastName,
    ) {}
}
