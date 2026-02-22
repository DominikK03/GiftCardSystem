<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\Command\UpdateProfileCommand;
use App\Application\User\Port\UserPersisterInterface;
use App\Application\User\Port\UserProviderInterface;
use App\Domain\User\ValueObject\UserId;

final class UpdateProfile
{
    public function __construct(
        private readonly UserProviderInterface $provider,
        private readonly UserPersisterInterface $persister,
    ) {}

    public function __invoke(UpdateProfileCommand $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->provider->loadFromId($userId);

        $user->updateProfile($command->firstName, $command->lastName);

        $this->persister->handle($user);
    }
}
