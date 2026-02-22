<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class UserProfile
{
    private function __construct(
        #[ORM\Column(type: 'string', length: 100, nullable: true)]
        private readonly ?string $firstName,
        #[ORM\Column(type: 'string', length: 100, nullable: true)]
        private readonly ?string $lastName,
    ) {
    }

    public static function create(?string $firstName, ?string $lastName): self
    {
        return new self(
            $firstName !== null ? trim($firstName) : null,
            $lastName !== null ? trim($lastName) : null,
        );
    }

    public static function empty(): self
    {
        return new self(null, null);
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getFullName(): ?string
    {
        $parts = array_filter([$this->firstName, $this->lastName]);

        return $parts !== [] ? implode(' ', $parts) : null;
    }

    public function isEmpty(): bool
    {
        return $this->firstName === null && $this->lastName === null;
    }
}
