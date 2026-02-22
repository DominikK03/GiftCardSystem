<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\Command\ChangeUserRoleCommand;
use App\Application\User\Port\UserPersisterInterface;
use App\Application\User\Port\UserProviderInterface;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;

final class ChangeUserRole
{
    public function __construct(
        private readonly UserProviderInterface $provider,
        private readonly UserPersisterInterface $persister
    ) {}

    public function __invoke(ChangeUserRoleCommand $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->provider->loadFromId($userId);

        $newRole = UserRole::fromString($command->newRole);
        $user->changeRole($newRole);

        $this->persister->handle($user);
    }
}
