<?php

declare(strict_types=1);

namespace App\Domain\User\Port;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserId;

interface UserRepository
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(UserEmail $email): ?User;

    public function existsByEmail(UserEmail $email): bool;

    /**
     * @return User[]
     */
    public function findAll(int $page = 1, int $limit = 20): array;

    public function count(): int;

    public function countActive(): int;

    public function countInactive(): int;

    public function delete(User $user): void;
}
