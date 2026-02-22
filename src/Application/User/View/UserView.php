<?php

declare(strict_types=1);

namespace App\Application\User\View;

final readonly class UserView
{
    public function __construct(
        public string $id,
        public string $email,
        public string $role,
        public bool $isActive,
        public string $createdAt,
        public ?string $deactivatedAt
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt,
            'deactivatedAt' => $this->deactivatedAt,
        ];
    }
}
