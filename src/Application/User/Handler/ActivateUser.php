<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\Command\ActivateUserCommand;
use App\Application\User\Port\UserPersisterInterface;
use App\Application\User\Port\UserProviderInterface;
use App\Domain\User\ValueObject\UserId;

final class ActivateUser
{
    public function __construct(
        private readonly UserProviderInterface $provider,
        private readonly UserPersisterInterface $persister
    ) {}

    public function __invoke(ActivateUserCommand $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->provider->loadFromId($userId);

        $user->activate();

        $this->persister->handle($user);
    }
}
