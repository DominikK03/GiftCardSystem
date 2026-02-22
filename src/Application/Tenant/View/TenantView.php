<?php

declare(strict_types=1);

namespace App\Application\Tenant\View;

final readonly class TenantView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $nip,
        public string $street,
        public string $city,
        public string $postalCode,
        public string $country,
        public string $phoneNumber,
        public string $representativeFirstName,
        public string $representativeLastName,
        public string $apiKey,
        public string $status,
        public string $createdAt,
        public ?string $suspendedAt = null,
        public ?string $cancelledAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'nip' => $this->nip,
            'address' => [
                'street' => $this->street,
                'city' => $this->city,
                'postalCode' => $this->postalCode,
                'country' => $this->country
            ],
            'phoneNumber' => $this->phoneNumber,
            'representative' => [
                'firstName' => $this->representativeFirstName,
                'lastName' => $this->representativeLastName
            ],
            'apiKey' => $this->apiKey,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'suspendedAt' => $this->suspendedAt,
            'cancelledAt' => $this->cancelledAt
        ];
    }
}
