<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AttemptEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttemptEvent>
 */
class AttemptEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttemptEvent::class);
    }
}
