<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Persistence\Doctrine;

use App\Domain\Tenant\Entity\TenantDocument;
use App\Domain\Tenant\Enum\DocumentType;
use App\Domain\Tenant\Port\TenantDocumentRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class TenantDocumentRepository implements TenantDocumentRepositoryInterface
{
    /** @var EntityRepository<TenantDocument> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(TenantDocument::class);
    }

    public function save(TenantDocument $document): void
    {
        $this->entityManager->persist($document);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?TenantDocument
    {
        return $this->repository->find($id);
    }

    public function findByTenantId(string $tenantId): array
    {
        return $this->repository->findBy(
            ['tenantId' => $tenantId],
            ['createdAt' => 'DESC']
        );
    }

    public function findByTenantIdAndType(string $tenantId, DocumentType $type): array
    {
        return $this->repository->findBy(
            ['tenantId' => $tenantId, 'type' => $type],
            ['createdAt' => 'DESC']
        );
    }

    public function getNextInvoiceNumber(int $year, int $month): int
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = (new \DateTimeImmutable($startDate))->modify('first day of next month')->format('Y-m-d');

        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(TenantDocument::class, 'd')
            ->where('d.type = :type')
            ->andWhere('d.createdAt >= :startDate')
            ->andWhere('d.createdAt < :endDate')
            ->setParameter('type', DocumentType::INVOICE)
            ->setParameter('startDate', new \DateTimeImmutable($startDate))
            ->setParameter('endDate', new \DateTimeImmutable($endDate))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count + 1;
    }

    public function countInvoicesSince(\DateTimeImmutable $since): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(TenantDocument::class, 'd')
            ->where('d.type = :type')
            ->andWhere('d.createdAt >= :since')
            ->setParameter('type', DocumentType::INVOICE)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
