<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handler;

use App\Application\Tenant\Command\SuspendTenantCommand;
use App\Application\Tenant\Port\TenantPersisterInterface;
use App\Application\Tenant\Port\TenantProviderInterface;
use App\Domain\Tenant\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SuspendTenant
{
    public function __construct(
        private readonly TenantProviderInterface $provider,
        private readonly TenantPersisterInterface $persister
    ) {
    }

    public function __invoke(SuspendTenantCommand $command): void
    {
        $tenant = $this->provider->loadById(
            TenantId::fromString($command->tenantId)
        );

        $tenant->suspend();

        $this->persister->handle($tenant);
    }
}
