<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Port\UserPersisterInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Port\UserRepository;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class RegisterUser
{
    public function __construct(
        private readonly UserPersisterInterface $persister,
        private readonly UserRepository $repository,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory
    ) {}

    public function __invoke(RegisterUserCommand $command): string
    {
        $email = UserEmail::fromString($command->email);

        if ($this->repository->existsByEmail($email)) {
            throw UserAlreadyExistsException::withEmail($command->email);
        }

        $userId = UserId::generate();
        $role = UserRole::fromString($command->role);

        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
        $passwordHash = $hasher->hash($command->password);

        $user = User::register(
            $userId,
            $email,
            $passwordHash,
            $role
        );

        $this->persister->handle($user);

        return $userId->toString();
    }
}
