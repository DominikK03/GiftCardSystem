<?php

declare(strict_types=1);

namespace App\Application\User\Provider;

use App\Application\User\Port\UserProviderInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Port\UserRepository;
use App\Domain\User\ValueObject\UserId;

final class UserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepository $repository
    ) {}

    public function loadFromId(UserId $id): User
    {
        $user = $this->repository->findById($id);

        if ($user === null) {
            throw UserNotFoundException::forId($id);
        }

        return $user;
    }
}
