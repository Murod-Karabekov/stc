<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ExamAttempt;
use App\Repository\ExamAttemptRepository;
use App\Repository\ExamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tanlovlar')]
final class TanlovlarController extends AbstractController
{
    public function __construct(
        private readonly ExamRepository $examRepository,
        private readonly ExamAttemptRepository $attemptRepository,
    ) {
    }

    #[Route('', name: 'app_tanlovlar', methods: ['GET'])]
    public function index(): Response
    {
        $exams = $this->examRepository->findAll();

        return $this->render('tanlovlar/index.html.twig', [
            'exams' => $exams,
        ]);
    }

    #[Route('/{code}', name: 'app_tanlov_show', methods: ['GET'])]
    public function show(string $code): Response
    {
        $exam = $this->examRepository->findByCode($code);
        if (!$exam) {
            throw $this->createNotFoundException('Tanlov topilmadi.');
        }

        return $this->render('tanlovlar/show.html.twig', [
            'exam' => $exam,
        ]);
    }

    #[Route('/{code}/natijalar', name: 'app_tanlov_natijalar', methods: ['GET'])]
    public function natijalar(string $code, Request $request): Response
    {
        $exam = $this->examRepository->findByCode($code);
        if (!$exam || !$exam->isPublished()) {
            throw $this->createNotFoundException('Natijalar hali e\'lon qilinmagan.');
        }

        // Only finalized attempts, ordered by score DESC
        $attempts = $this->attemptRepository->createQueryBuilder('a')
            ->where('a.exam = :exam')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('exam', $exam)
            ->setParameter('statuses', [ExamAttempt::STATUS_SUBMITTED, ExamAttempt::STATUS_EXPIRED])
            ->orderBy('a.totalScore', 'DESC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();

        // Build ranked list with 1,2,2,4 convention
        $ranked = [];
        $rank = 1;
        $prevScore = null;
        $prevRank = 1;
        foreach ($attempts as $attempt) {
            $score = $attempt->getTotalScore();
            if ($prevScore !== null && $score === $prevScore) {
                $displayRank = $prevRank;
            } else {
                $displayRank = $rank;
                $prevRank = $rank;
            }
            $ranked[] = ['rank' => $displayRank, 'attempt' => $attempt];
            $prevScore = $score;
            $rank++;
        }

        // Candidate-code search
        $searchCode = trim((string) $request->query->get('kod', ''));
        $found = null;
        if ($searchCode !== '') {
            foreach ($ranked as $row) {
                if ($row['attempt']->getCandidateCode() === $searchCode) {
                    $found = $row;
                    break;
                }
            }
        }

        return $this->render('tanlovlar/natijalar.html.twig', [
            'exam' => $exam,
            'ranked' => $ranked,
            'searchCode' => $searchCode,
            'found' => $found,
        ]);
    }
}
