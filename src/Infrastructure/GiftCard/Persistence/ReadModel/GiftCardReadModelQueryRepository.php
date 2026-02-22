<?php

declare(strict_types=1);

namespace App\Infrastructure\GiftCard\Persistence\ReadModel;

use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use App\Application\GiftCard\ReadModel\GiftCardReadModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class GiftCardReadModelQueryRepository extends ServiceEntityRepository implements GiftCardReadModelRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GiftCardReadModel::class);
    }

    public function findById(string $id): ?GiftCardReadModel
    {
        return $this->find($id);
    }

    public function findByCardNumber(string $cardNumber): ?GiftCardReadModel
    {
        return $this->findOneBy(['cardNumber' => $cardNumber]);
    }

    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    public function findExpiring(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.expiresAt IS NOT NULL')
            ->andWhere('g.expiresAt <= :before')
            ->andWhere('g.status = :status')
            ->setParameter('before', $before)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getResult();
    }

    public function findByTenant(string $tenantId, int $page, int $limit, ?string $status): array
    {
        $qb = $this->createQueryBuilder('g')
            ->where('g.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId)
            ->orderBy('g.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($status !== null) {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByTenant(string $tenantId, ?string $status): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId);

        if ($status !== null) {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findAllPaginated(int $page, int $limit, ?string $status, ?string $tenantId = null, ?string $id = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->orderBy('g.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $this->applyFilters($qb, $status, $tenantId, $id);

        return $qb->getQuery()->getResult();
    }

    public function countAll(?string $status, ?string $tenantId = null, ?string $id = null): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)');

        $this->applyFilters($qb, $status, $tenantId, $id);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getTotalActiveBalance(): array
    {
        return $this->createQueryBuilder('g')
            ->select('SUM(g.balanceAmount) AS total, g.balanceCurrency AS currency')
            ->where('g.status = :status')
            ->setParameter('status', 'active')
            ->groupBy('g.balanceCurrency')
            ->getQuery()
            ->getResult();
    }

    public function getTotalRedeemed(): array
    {
        return $this->createQueryBuilder('g')
            ->select('SUM(g.initialAmount - g.balanceAmount) AS total, g.initialCurrency AS currency')
            ->where('g.initialAmount > g.balanceAmount')
            ->groupBy('g.initialCurrency')
            ->getQuery()
            ->getResult();
    }

    public function countExpiringSoon(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.status = :status')
            ->andWhere('g.expiresAt IS NOT NULL')
            ->andWhere('g.expiresAt > :now')
            ->andWhere('g.expiresAt <= :threshold')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecentActivity(int $limit): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTopTenantsByActiveCards(int $limit): array
    {
        return $this->createQueryBuilder('g')
            ->select('g.tenantId AS tenantId, COUNT(g.id) AS cardCount, SUM(g.balanceAmount) AS totalBalance, g.balanceCurrency AS currency')
            ->where('g.status = :status')
            ->setParameter('status', 'active')
            ->groupBy('g.tenantId, g.balanceCurrency')
            ->orderBy('cardCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, ?string $status, ?string $tenantId, ?string $id): void
    {
        if ($status !== null) {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        if ($tenantId !== null) {
            $qb->andWhere('g.tenantId = :tenantId')
                ->setParameter('tenantId', $tenantId);
        }

        if ($id !== null) {
            $qb->andWhere('g.id LIKE :id')
                ->setParameter('id', $id . '%');
        }
    }
}
