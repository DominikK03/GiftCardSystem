<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\Command\DeactivateUserCommand;
use App\Application\User\Port\UserPersisterInterface;
use App\Application\User\Port\UserProviderInterface;
use App\Domain\User\ValueObject\UserId;

final class DeactivateUser
{
    public function __construct(
        private readonly UserProviderInterface $provider,
        private readonly UserPersisterInterface $persister
    ) {}

    public function __invoke(DeactivateUserCommand $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->provider->loadFromId($userId);

        $user->deactivate();

        $this->persister->handle($user);
    }
}
