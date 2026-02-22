<?php

declare(strict_types=1);

namespace App\Interface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTenantRequest
{
    #[Assert\NotBlank(message: 'name is required')]
    #[Assert\Type(type: 'string')]
    #[Assert\Length(min: 2, max: 255)]
    public mixed $name = null;

    #[Assert\NotBlank(message: 'email is required')]
    #[Assert\Type(type: 'string')]
    #[Assert\Email]
    public mixed $email = null;

    #[Assert\NotBlank(message: 'nip is required')]
    #[Assert\Type(type: 'string')]
    #[Assert\Regex(pattern: '/^\d{10}$/', message: 'NIP must be 10 digits')]
    public mixed $nip = null;

    #[Assert\NotBlank(message: 'street is required')]
    #[Assert\Type(type: 'string')]
    public mixed $street = null;

    #[Assert\NotBlank(message: 'city is required')]
    #[Assert\Type(type: 'string')]
    public mixed $city = null;

    #[Assert\NotBlank(message: 'postalCode is required')]
    #[Assert\Type(type: 'string')]
    public mixed $postalCode = null;

    #[Assert\NotBlank(message: 'country is required')]
    #[Assert\Type(type: 'string')]
    public mixed $country = null;

    #[Assert\NotBlank(message: 'phoneNumber is required')]
    #[Assert\Type(type: 'string')]
    #[Assert\Regex(pattern: '/^\+?[1-9]\d{1,14}$/', message: 'Invalid phone number format')]
    public mixed $phoneNumber = null;

    #[Assert\NotBlank(message: 'representativeFirstName is required')]
    #[Assert\Type(type: 'string')]
    public mixed $representativeFirstName = null;

    #[Assert\NotBlank(message: 'representativeLastName is required')]
    #[Assert\Type(type: 'string')]
    public mixed $representativeLastName = null;

    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->name = $data['name'] ?? null;
        $self->email = $data['email'] ?? null;
        $self->nip = $data['nip'] ?? null;
        $self->street = $data['street'] ?? null;
        $self->city = $data['city'] ?? null;
        $self->postalCode = $data['postalCode'] ?? null;
        $self->country = $data['country'] ?? null;
        $self->phoneNumber = $data['phoneNumber'] ?? null;
        $self->representativeFirstName = $data['representativeFirstName'] ?? null;
        $self->representativeLastName = $data['representativeLastName'] ?? null;

        return $self;
    }
}
