<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

use App\Domain\Tenant\Exception\InvalidAddressException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class Address
{
    private function __construct(
        #[ORM\Column(type: 'string', length: 255)]
        private readonly string $street,
        #[ORM\Column(type: 'string', length: 100)]
        private readonly string $city,
        #[ORM\Column(type: 'string', length: 20)]
        private readonly string $postalCode,
        #[ORM\Column(type: 'string', length: 100)]
        private readonly string $country
    ) {
    }

    public static function create(
        string $street,
        string $city,
        string $postalCode,
        string $country
    ): self {
        if (empty(trim($street))) {
            throw InvalidAddressException::emptyField('street');
        }

        if (empty(trim($city))) {
            throw InvalidAddressException::emptyField('city');
        }

        if (empty(trim($postalCode))) {
            throw InvalidAddressException::emptyField('postalCode');
        }

        if (empty(trim($country))) {
            throw InvalidAddressException::emptyField('country');
        }

        return new self(
            trim($street),
            trim($city),
            trim($postalCode),
            trim($country)
        );
    }

    public function equals(Address $other): bool
    {
        return $this->street === $other->street
            && $this->city === $other->city
            && $this->postalCode === $other->postalCode
            && $this->country === $other->country;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function toString(): string
    {
        return sprintf(
            '%s, %s %s, %s',
            $this->street,
            $this->postalCode,
            $this->city,
            $this->country
        );
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
