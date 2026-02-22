<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\Command\ChangePasswordCommand;
use App\Application\User\Port\UserPersisterInterface;
use App\Application\User\Port\UserProviderInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserId;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class ChangePassword
{
    public function __construct(
        private readonly UserProviderInterface $provider,
        private readonly UserPersisterInterface $persister,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory
    ) {}

    public function __invoke(ChangePasswordCommand $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->provider->loadFromId($userId);

        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
        $newPasswordHash = $hasher->hash($command->newPassword);

        $user->changePassword($newPasswordHash);

        $this->persister->handle($user);
    }
}
