<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Persistence\Doctrine;

use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\Exception\TenantNotFoundException;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Domain\Tenant\ValueObject\TenantEmail;
use App\Domain\Tenant\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
final class TenantQueryRepository implements TenantQueryRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(Tenant::class);
    }

    public function findById(TenantId $id): Tenant
    {
        $tenant = $this->repository->find($id->toString());

        if (!$tenant instanceof Tenant) {
            throw TenantNotFoundException::withId($id);
        }

        return $tenant;
    }

    public function findByEmail(TenantEmail $email): ?Tenant
    {
        return $this->repository->findOneBy(['email' => $email->toString()]);
    }

    public function existsByEmail(TenantEmail $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function findByApiKey(string $apiKey): ?Tenant
    {
        return $this->repository->findOneBy(['apiKey' => $apiKey]);
    }
}
