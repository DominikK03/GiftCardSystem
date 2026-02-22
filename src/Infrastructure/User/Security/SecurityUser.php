<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Security;

use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private readonly User $domainUser
    ) {}

    public function getUserIdentifier(): string
    {
        return $this->domainUser->getEmail()->toString();
    }

    public function getRoles(): array
    {
        $role = 'ROLE_' . $this->domainUser->getRole()->toString();

        return [$role];
    }

    public function getPassword(): ?string
    {
        return $this->domainUser->getPasswordHash();
    }

    public function eraseCredentials(): void
    {
    }

    public function getDomainUser(): User
    {
        return $this->domainUser;
    }
}
