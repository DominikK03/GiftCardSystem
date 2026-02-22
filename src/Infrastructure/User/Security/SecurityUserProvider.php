<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Security;

use App\Domain\User\ValueObject\UserEmail;
use App\Infrastructure\User\Persistence\DoctrineUserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class SecurityUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly DoctrineUserRepository $userRepository
    ) {}

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $email = UserEmail::fromString($identifier);
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        if (!$user->isActive()) {
            throw new UserNotFoundException(sprintf('User "%s" is deactivated.', $identifier));
        }

        return new SecurityUser($user);
    }
}
