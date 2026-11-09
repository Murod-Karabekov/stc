<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ExamAttempt;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/attempt')]
final class AttemptDecisionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Route('/{id}/decision', name: 'admin_attempt_decision', methods: ['GET', 'POST'])]
    public function decide(ExamAttempt $attempt, Request $request): Response
    {
        if ($attempt->getStatus() === ExamAttempt::STATUS_IN_PROGRESS) {
            $this->addFlash('warning', 'Jarayondagi urinish uchun qaror qo\'yib bo\'lmaydi.');
            return $this->redirectToRoute('admin');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('decision_' . $attempt->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Noto\'g\'ri CSRF token.');
                return $this->redirectToRoute('admin');
            }

            $decision = (string) $request->request->get('decision');
            $allowed = [
                ExamAttempt::DECISION_PENDING,
                ExamAttempt::DECISION_ADVANCED,
                ExamAttempt::DECISION_RESERVE,
                ExamAttempt::DECISION_REJECTED,
            ];
            if (!in_array($decision, $allowed, true)) {
                $this->addFlash('danger', 'Noto\'g\'ri qaror qiymati.');
                return $this->redirectToRoute('admin');
            }

            $adminName = $this->tokenStorage->getToken()?->getUserIdentifier() ?? 'admin';
            $attempt->setSelectionDecision($decision, $adminName);
            $this->em->flush();

            $this->addFlash('success', 'Qaror saqlandi.');
            return $this->redirectToRoute('admin_attempt_decision', ['id' => $attempt->getId()]);
        }

        return $this->render('admin/attempt_decision.html.twig', [
            'attempt' => $attempt,
            'decisions' => [
                ExamAttempt::DECISION_PENDING => 'Kutilmoqda',
                ExamAttempt::DECISION_ADVANCED => 'O\'tdi',
                ExamAttempt::DECISION_RESERVE => 'Zaxira',
                ExamAttempt::DECISION_REJECTED => 'Rad etildi',
            ],
        ]);
    }
}
