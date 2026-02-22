<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handler;

use App\Application\Tenant\Command\CreateTenantCommand;
use App\Application\Tenant\Port\TenantPersisterInterface;
use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\ValueObject\Address;
use App\Domain\Tenant\ValueObject\ApiKey;
use App\Domain\Tenant\ValueObject\ApiSecret;
use App\Domain\Tenant\ValueObject\NIP;
use App\Domain\Tenant\ValueObject\PhoneNumber;
use App\Domain\Tenant\ValueObject\RepresentativeName;
use App\Domain\Tenant\ValueObject\TenantEmail;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Domain\Tenant\ValueObject\TenantName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateTenant
{
    public function __construct(
        private readonly TenantPersisterInterface $persister
    ) {
    }

    public function __invoke(CreateTenantCommand $command): string
    {
        $tenantId = TenantId::generate();

        $tenant = Tenant::create(
            $tenantId,
            TenantName::fromString($command->name),
            TenantEmail::fromString($command->email),
            NIP::fromString($command->nip),
            Address::create(
                $command->street,
                $command->city,
                $command->postalCode,
                $command->country
            ),
            PhoneNumber::fromString($command->phoneNumber),
            RepresentativeName::create(
                $command->representativeFirstName,
                $command->representativeLastName
            ),
            ApiKey::generate(),
            ApiSecret::generate()
        );

        $this->persister->handle($tenant);

        return $tenantId->toString();
    }
}
