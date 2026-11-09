<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Exam;
use App\Entity\ExamAttempt;
use App\Repository\ExamAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/exam')]
final class ExamPublishController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExamAttemptRepository $attemptRepository,
    ) {
    }

    #[Route('/{id}/publish-confirm', name: 'admin_exam_publish_confirm', methods: ['GET'])]
    public function confirm(Exam $exam): Response
    {
        if ($exam->isPublished()) {
            $this->addFlash('warning', 'Natijalar allaqachon e\'lon qilingan.');
            return $this->redirectToRoute('admin');
        }

        $finalizedCount = $this->attemptRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.exam = :exam')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('exam', $exam)
            ->setParameter('statuses', [ExamAttempt::STATUS_SUBMITTED, ExamAttempt::STATUS_EXPIRED])
            ->getQuery()
            ->getSingleScalarResult();

        $pendingDecisionCount = $this->attemptRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.exam = :exam')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.selectionDecision = :pending')
            ->setParameter('exam', $exam)
            ->setParameter('statuses', [ExamAttempt::STATUS_SUBMITTED, ExamAttempt::STATUS_EXPIRED])
            ->setParameter('pending', ExamAttempt::DECISION_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/exam_publish_confirm.html.twig', [
            'exam' => $exam,
            'finalizedCount' => (int) $finalizedCount,
            'pendingDecisionCount' => (int) $pendingDecisionCount,
        ]);
    }

    #[Route('/{id}/publish', name: 'admin_exam_publish', methods: ['POST'])]
    public function publish(Exam $exam, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('publish_exam_' . $exam->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Noto\'g\'ri CSRF token.');
            return $this->redirectToRoute('admin');
        }

        if ($exam->isPublished()) {
            $this->addFlash('warning', 'Natijalar allaqachon e\'lon qilingan.');
            return $this->redirectToRoute('admin');
        }

        $exam->setIsPublished(true);
        $exam->setPublishedAt(new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')));
        $this->em->flush();

        $this->addFlash('success', sprintf('"%s" uchun natijalar muvaffaqiyatli e\'lon qilindi.', $exam->getTitle()));
        return $this->redirectToRoute('admin');
    }
}
