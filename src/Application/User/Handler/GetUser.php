<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\Query\GetUserQuery;
use App\Application\User\View\UserView;
use App\Domain\User\Port\UserRepository;
use App\Domain\User\ValueObject\UserId;

final class GetUser
{
    public function __construct(
        private readonly UserRepository $repository
    ) {}

    public function __invoke(GetUserQuery $query): ?UserView
    {
        $user = $this->repository->findById(UserId::fromString($query->id));

        if ($user === null) {
            return null;
        }

        return new UserView(
            id: $user->getId()->toString(),
            email: $user->getEmail()->toString(),
            role: $user->getRole()->toString(),
            isActive: $user->isActive(),
            createdAt: $user->getCreatedAt()->format('c'),
            deactivatedAt: $user->getDeactivatedAt()?->format('c')
        );
    }
}
