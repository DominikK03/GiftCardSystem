<?php

declare(strict_types=1);

namespace App\Infrastructure\Activation\Repository;

use App\Application\Activation\Port\CardAssignmentCheckerInterface;
use App\Infrastructure\Activation\Entity\CardAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class CardAssignmentRepository extends ServiceEntityRepository implements CardAssignmentCheckerInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardAssignment::class);
    }

    public function isCardAssigned(string $giftCardId): bool
    {
        return $this->findOneBy(['giftCardId' => $giftCardId]) !== null;
    }

    /** @return CardAssignment[] */
    public function findByCustomerEmail(string $customerEmail): array
    {
        return $this->findBy(['customerEmail' => $customerEmail]);
    }
}
