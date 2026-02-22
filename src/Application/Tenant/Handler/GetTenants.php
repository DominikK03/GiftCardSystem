<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handler;

use App\Application\Tenant\Query\GetTenantsQuery;
use App\Application\Tenant\View\TenantView;
use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetTenants
{
    public function __construct(
        private readonly TenantQueryRepositoryInterface $repository
    ) {}

    /**
     * @return array{tenants: TenantView[], total: int, page: int, limit: int, totalPages: int}
     */
    public function __invoke(GetTenantsQuery $query): array
    {
        $allTenants = $this->repository->findAll();
        $total = count($allTenants);

        $offset = ($query->page - 1) * $query->limit;
        $tenants = array_slice($allTenants, $offset, $query->limit);

        $tenantViews = array_map(
            function(Tenant $tenant): TenantView {
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
            },
            $tenants
        );

        return [
            'tenants' => $tenantViews,
            'total' => $total,
            'page' => $query->page,
            'limit' => $query->limit,
            'totalPages' => (int) ceil($total / $query->limit)
        ];
    }
}
