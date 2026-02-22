<?php

declare(strict_types=1);

namespace App\Application\Tenant\Command;

final readonly class CreateTenantCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $nip,
        public string $street,
        public string $city,
        public string $postalCode,
        public string $country,
        public string $phoneNumber,
        public string $representativeFirstName,
        public string $representativeLastName
    ) {
    }
}
