<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Section;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Section>
 */
class SectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Section::class);
    }

    /**
     * @return list<Section>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC']);
    }

    public function findByPosition(int $position): ?Section
    {
        return $this->findOneBy(['position' => $position]);
    }
}
