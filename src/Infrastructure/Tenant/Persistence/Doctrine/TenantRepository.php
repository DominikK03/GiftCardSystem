<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Persistence\Doctrine;

use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\Port\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
final class TenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(Tenant $tenant): void
    {
        $this->entityManager->persist($tenant);
        $this->entityManager->flush();
    }
}
