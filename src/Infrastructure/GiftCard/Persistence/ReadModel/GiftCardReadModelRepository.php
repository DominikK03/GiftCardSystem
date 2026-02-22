<?php

declare(strict_types=1);

namespace App\Infrastructure\GiftCard\Persistence\ReadModel;

use App\Application\GiftCard\Port\GiftCardReadModelWriterInterface;
use App\Application\GiftCard\ReadModel\GiftCardReadModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class GiftCardReadModelRepository extends ServiceEntityRepository implements GiftCardReadModelWriterInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GiftCardReadModel::class);
    }

    public function save(GiftCardReadModel $giftCard): void
    {
        $this->getEntityManager()->persist($giftCard);
        $this->getEntityManager()->flush();
    }
}
