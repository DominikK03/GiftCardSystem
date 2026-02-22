<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\Exception\InsufficientPermissionsException;
use App\Domain\User\Exception\UserAlreadyDeactivatedException;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserProfile;
use App\Domain\User\ValueObject\UserRole;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255)]
    private string $passwordHash;

    #[ORM\Column(type: 'string', length: 20)]
    private string $role;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deactivatedAt = null;

    #[ORM\Embedded(class: UserProfile::class, columnPrefix: 'profile_')]
    private UserProfile $profile;

    private function __construct(
        UserId $id,
        UserEmail $email,
        string $passwordHash,
        UserRole $role,
        DateTimeImmutable $createdAt
    ) {
        $this->id = $id->toString();
        $this->email = $email->toString();
        $this->passwordHash = $passwordHash;
        $this->role = $role->toString();
        $this->isActive = true;
        $this->createdAt = $createdAt;
        $this->profile = UserProfile::empty();
    }

    public static function register(
        UserId $id,
        UserEmail $email,
        string $passwordHash,
        UserRole $role,
        ?DateTimeImmutable $createdAt = null
    ): self {
        return new self(
            $id,
            $email,
            $passwordHash,
            $role,
            $createdAt ?? new DateTimeImmutable()
        );
    }

    public function changeRole(UserRole $newRole): void
    {
        $this->role = $newRole->toString();
    }

    public function deactivate(?DateTimeImmutable $deactivatedAt = null): void
    {
        if (!$this->isActive) {
            throw UserAlreadyDeactivatedException::create();
        }

        $this->isActive = false;
        $this->deactivatedAt = $deactivatedAt ?? new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->deactivatedAt = null;
    }

    public function changePassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
    }

    public function updateProfile(?string $firstName, ?string $lastName): void
    {
        $this->profile = UserProfile::create($firstName, $lastName);
    }

    public function getProfile(): UserProfile
    {
        return $this->profile;
    }

    public function getId(): UserId
    {
        return UserId::fromString($this->id);
    }

    public function getEmail(): UserEmail
    {
        return UserEmail::fromString($this->email);
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): UserRole
    {
        return UserRole::fromString($this->role);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function canManageUsers(): bool
    {
        return $this->getRole()->canManageUsers();
    }

    public function canManageTenants(): bool
    {
        return $this->getRole()->canManageTenants();
    }

    public function canManageGiftCards(): bool
    {
        return $this->getRole()->canManageGiftCards();
    }

    public function ensureCanManageUsers(): void
    {
        if (!$this->canManageUsers()) {
            throw InsufficientPermissionsException::onlyOwnerCanManageUsers();
        }
    }

    public function ensureCanManageTenants(): void
    {
        if (!$this->canManageTenants()) {
            throw InsufficientPermissionsException::forAction('manage_tenants', $this->getRole());
        }
    }
}
