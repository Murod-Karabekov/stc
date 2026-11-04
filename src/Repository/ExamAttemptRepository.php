<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exam;
use App\Entity\ExamAttempt;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
