<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Security;

use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\Enum\TenantStatus;
use App\Domain\Tenant\Exception\TenantAuthenticationException;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Infrastructure\Tenant\TenantContext;

final class TenantAuthenticator
{
    public function __construct(
        private readonly TenantQueryRepositoryInterface $queryRepository,
        private readonly TenantContext $tenantContext
    ) {
    }

    /**
     * @throws TenantAuthenticationException
     */
    public function authenticate(string $apiKey): Tenant
    {
        $tenant = $this->queryRepository->findByApiKey($apiKey);

        if ($tenant === null) {
            throw TenantAuthenticationException::invalidApiKey($apiKey);
        }

        if ($tenant->getStatus() === TenantStatus::SUSPENDED) {
            throw TenantAuthenticationException::tenantSuspended();
        }

        if ($tenant->getStatus() === TenantStatus::CANCELLED) {
            throw TenantAuthenticationException::tenantCancelled();
        }

        $this->tenantContext->setTenantId($tenant->getId());

        return $tenant;
    }
}
