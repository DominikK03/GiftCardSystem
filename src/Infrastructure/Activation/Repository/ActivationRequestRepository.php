<?php

declare(strict_types=1);

namespace App\Infrastructure\Activation\Repository;

use App\Infrastructure\Activation\Entity\ActivationRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ActivationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivationRequest::class);
    }
}
