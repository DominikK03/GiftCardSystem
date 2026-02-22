<?php

declare(strict_types=1);

namespace App\Application\User\Persister;

use App\Application\User\Port\UserPersisterInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Port\UserRepository;

final class UserPersister implements UserPersisterInterface
{
    public function __construct(
        private readonly UserRepository $repository
    ) {}

    public function handle(User $user): void
    {
        $this->repository->save($user);
    }
}
