<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\Query\GetUsersQuery;
use App\Application\User\View\UserView;
use App\Domain\User\Port\UserRepository;

final class GetUsers
{
    public function __construct(
        private readonly UserRepository $repository
    ) {}

    public function __invoke(GetUsersQuery $query): array
    {
        $users = $this->repository->findAll($query->page, $query->limit);
        $total = $this->repository->count();

        $userViews = array_map(
            fn($user) => new UserView(
                id: $user->getId()->toString(),
                email: $user->getEmail()->toString(),
                role: $user->getRole()->toString(),
                isActive: $user->isActive(),
                createdAt: $user->getCreatedAt()->format('c'),
                deactivatedAt: $user->getDeactivatedAt()?->format('c')
            ),
            $users
        );

        return [
            'users' => array_map(fn($view) => $view->toArray(), $userViews),
            'total' => $total,
            'page' => $query->page,
            'limit' => $query->limit,
            'totalPages' => (int) ceil($total / $query->limit)
        ];
    }
}
