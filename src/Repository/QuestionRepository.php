<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exam;
use App\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /**
     * @return list<Question>
     */
    public function findReadyByExam(Exam $exam): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.exam = :exam')
            ->andWhere('q.status = :status')
            ->setParameter('exam', $exam)
            ->setParameter('status', Question::STATUS_READY)
            ->leftJoin('q.section', 's')
            ->addOrderBy('s.position', 'ASC')
            ->addOrderBy('q.questionNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
