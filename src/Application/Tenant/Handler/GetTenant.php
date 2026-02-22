<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handler;

use App\Application\Tenant\Query\GetTenantQuery;
use App\Application\Tenant\View\TenantView;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Domain\Tenant\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetTenant
{
    public function __construct(
        private readonly TenantQueryRepositoryInterface $repository
    ) {}

    public function __invoke(GetTenantQuery $query): TenantView
    {
        $tenant = $this->repository->findById(TenantId::fromString($query->id));
        $address = $tenant->getAddress();
        $representative = $tenant->getRepresentativeName();

        return new TenantView(
            id: $tenant->getId()->toString(),
            name: $tenant->getName()->toString(),
            email: $tenant->getEmail()->toString(),
            nip: $tenant->getNIP()->toString(),
            street: $address->getStreet(),
            city: $address->getCity(),
            postalCode: $address->getPostalCode(),
            country: $address->getCountry(),
            phoneNumber: $tenant->getPhoneNumber()->toString(),
            representativeFirstName: $representative->getFirstName(),
            representativeLastName: $representative->getLastName(),
            apiKey: $tenant->getApiKey()->toString(),
            status: $tenant->getStatus()->value,
            createdAt: $tenant->getCreatedAt()->format('c'),
            suspendedAt: $tenant->getSuspendedAt()?->format('c'),
            cancelledAt: $tenant->getCancelledAt()?->format('c')
        );
    }
}
