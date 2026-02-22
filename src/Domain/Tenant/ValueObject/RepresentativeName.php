<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

use App\Domain\Tenant\Exception\InvalidRepresentativeNameException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class RepresentativeName
{
    private function __construct(
        #[ORM\Column(type: 'string', length: 100)]
        private readonly string $firstName,
        #[ORM\Column(type: 'string', length: 100)]
        private readonly string $lastName
    ) {
    }

    public static function create(string $firstName, string $lastName): self
    {
        $trimmedFirstName = trim($firstName);
        $trimmedLastName = trim($lastName);

        if (empty($trimmedFirstName)) {
            throw InvalidRepresentativeNameException::emptyFirstName();
        }

        if (empty($trimmedLastName)) {
            throw InvalidRepresentativeNameException::emptyLastName();
        }

        return new self($trimmedFirstName, $trimmedLastName);
    }

    public function equals(RepresentativeName $other): bool
    {
        return $this->firstName === $other->firstName
            && $this->lastName === $other->lastName;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
