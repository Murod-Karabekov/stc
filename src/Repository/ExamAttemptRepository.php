<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exam;
use App\Entity\ExamAttempt;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExamAttempt>
 */
class ExamAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamAttempt::class);
    }

    public function findByCandidateAndExam(User $candidate, Exam $exam): ?ExamAttempt
    {
        return $this->findOneBy(['candidate' => $candidate, 'exam' => $exam]);
    }

    public function findByCandidateCode(string $code): ?ExamAttempt
    {
        return $this->findOneBy(['candidateCode' => $code]);
    }

    /**
     * QueryBuilder pre-filtered to exclude candidates who have ROLE_ADMIN.
     * Use this as the base for all public stats, ranking, and exports so
     * admin-owned test attempts are never counted alongside real candidates.
     */
    public function createEligibleQueryBuilder(string $alias = 'a'): QueryBuilder
    {
        $adminIds = $this->getEntityManager()
            ->getConnection()
            ->fetchFirstColumn(
                "SELECT id FROM stc_users WHERE roles::text LIKE '%ROLE_ADMIN%'"
            );

        $qb = $this->createQueryBuilder($alias);
        if (!empty($adminIds)) {
            $qb->andWhere("IDENTITY({$alias}.candidate) NOT IN (:_eligExcludedIds)")
               ->setParameter('_eligExcludedIds', $adminIds);
        }

        return $qb;
    }

    /**
     * @return list<ExamAttempt>
     */
    public function findExpiredActiveAttempts(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.deadlineAt < :now')
            ->setParameter('status', ExamAttempt::STATUS_IN_PROGRESS)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ExamAttempt>
     */
    public function findPendingSheetsSync(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status IN (:finalizedStatuses)')
            ->andWhere('a.isSyncedToSheets = false')
            ->setParameter('finalizedStatuses', [ExamAttempt::STATUS_SUBMITTED, ExamAttempt::STATUS_EXPIRED])
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
