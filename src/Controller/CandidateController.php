<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CandidateRegistrationDto;
use App\Entity\AttemptAnswer;
use App\Entity\ExamAttempt;
use App\Entity\User;
use App\Form\CandidateRegistrationType;
use App\Repository\AttemptAnswerRepository;
use App\Repository\ExamAttemptRepository;
use App\Repository\ExamRepository;
use App\Repository\UserRepository;
use App\Service\ExamAttemptService;
use App\Service\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CandidateController extends AbstractController
{
    public function __construct(
        private readonly ExamRepository $examRepository,
        private readonly UserRepository $userRepository,
        private readonly ExamAttemptRepository $attemptRepository,
        private readonly ExamAttemptService $attemptService,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/saralash', name: 'app_candidate_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $exam = $this->examRepository->findCurrentActiveExam();
        if ($exam === null || !$exam->isOpen()) {
            return $this->render('candidate/closed.html.twig', [
                'message' => 'Hozirda volontyorlik saralash jarayoniga yangi nomzodlar qabuli to\'xtatilgan.',
            ]);
        }

        // If candidate already authenticated, redirect to instructions or exam
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && in_array('ROLE_USER', $currentUser->getRoles(), true)) {
            $attempt = $this->attemptRepository->findByCandidateAndExam($currentUser, $exam);
            if ($attempt !== null && ($attempt->isSubmitted() || $attempt->isExpired())) {
                return $this->redirectToRoute('app_candidate_completed');
            }
            if ($attempt !== null && $attempt->isInProgress()) {
                return $this->redirectToRoute('app_candidate_exam');
            }
            return $this->redirectToRoute('app_candidate_instructions');
        }

        $dto = new CandidateRegistrationDto();
        $form = $this->createForm(CandidateRegistrationType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $normalizedPhone = PhoneNormalizer::normalize((string) $dto->phone);

            // Check if user already exists
            $existingUser = $this->userRepository->findByPhone($normalizedPhone);

            if ($existingUser !== null) {
                // If this is an existing user but from another session/device
                $this->addFlash('error', 'Ushbu telefon raqami avval ro\'yxatdan o\'tgan. Bir telefon raqamidan faqat bitta nomzod foydalanishi mumkin. Davom etish uchun avvalgi brauzeringizdan foydalaning yoki administratorga murojaat qiling.');
                return $this->render('candidate/register.html.twig', [
                    'form' => $form->createView(),
                    'exam' => $exam,
                ]);
            }

            // Create new candidate
            $user = new User();
            $user->setIdentifier($normalizedPhone);
            $user->setPhone($normalizedPhone);
            $user->setFullName($dto->fullName);
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Passwordless authentication into session & migrate session ID
            $this->security->login($user, 'form_login', 'main');
            if ($request->hasSession()) {
                $request->getSession()->migrate();
            }

            return $this->redirectToRoute('app_candidate_instructions');
        }

        return $this->render('candidate/register.html.twig', [
            'form' => $form->createView(),
            'exam' => $exam,
        ]);
    }

    #[Route('/saralash/yoriqnoma', name: 'app_candidate_instructions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function instructions(): Response
    {
        $exam = $this->examRepository->findCurrentActiveExam();
        if ($exam === null) {
            return $this->redirectToRoute('app_home');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User) {
            $attempt = $this->attemptRepository->findByCandidateAndExam($currentUser, $exam);
            if ($attempt !== null && ($attempt->isSubmitted() || $attempt->isExpired())) {
                return $this->redirectToRoute('app_candidate_completed');
            }
            if ($attempt !== null && $attempt->isInProgress()) {
                return $this->redirectToRoute('app_candidate_exam');
            }
        }

        return $this->render('candidate/instructions.html.twig', [
            'exam' => $exam,
        ]);
    }

    #[Route('/saralash/test', name: 'app_candidate_exam', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function exam(): Response
    {
        $exam = $this->examRepository->findCurrentActiveExam();
        if ($exam === null) {
            return $this->redirectToRoute('app_home');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Starts or resumes attempt (sets startedAt and deadlineAt on server)
        $attempt = $this->attemptService->startOrResumeAttempt($currentUser, $exam);

        if ($attempt->isSubmitted() || $attempt->isExpired()) {
            return $this->redirectToRoute('app_candidate_completed');
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone('Asia/Tashkent'));
        $remainingSeconds = max(0, $attempt->getDeadlineAt()->getTimestamp() - $now->getTimestamp());

        // Load existing answers
        $answers = $this->entityManager->getRepository(AttemptAnswer::class)->findBy(['attempt' => $attempt]);
        $savedAnswers = [];
        foreach ($answers as $ans) {
            $savedAnswers[$ans->getQuestionId()] = $ans->getSelectedOptionId();
        }

        return $this->render('candidate/exam.html.twig', [
            'attempt' => $attempt,
            'exam' => $exam,
            'remainingSeconds' => $remainingSeconds,
            'savedAnswers' => $savedAnswers,
            'questionsSnapshot' => $attempt->getQuestionSnapshot(),
        ]);
    }

    #[Route('/saralash/test/save', name: 'app_candidate_exam_save', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveAnswer(Request $request): JsonResponse
    {
        $exam = $this->examRepository->findCurrentActiveExam();
        if ($exam === null) {
            return new JsonResponse(['error' => 'Faol imtihon topilmadi'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $attempt = $this->attemptRepository->findByCandidateAndExam($currentUser, $exam);

        if ($attempt === null || !$attempt->isInProgress()) {
            return new JsonResponse(['error' => 'Urinish yakunlangan yoki topilmadi'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!isset($data['question_id'])) {
            return new JsonResponse(['error' => 'Savol identifikatori ko\'rsatilmagan'], Response::HTTP_BAD_REQUEST);
        }

        $questionId = (int) $data['question_id'];
        $optionId = isset($data['option_id']) && $data['option_id'] !== null ? (int) $data['option_id'] : null;
        $clientRevision = (int) ($data['client_revision'] ?? 1);

        try {
            $result = $this->attemptService->saveAnswer($attempt, $questionId, $optionId, $clientRevision);
            return new JsonResponse($result);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'status' => 'expired'], Response::HTTP_GONE);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/saralash/test/telemetry', name: 'app_candidate_exam_telemetry', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function recordTelemetry(Request $request): JsonResponse
    {
        $exam = $this->examRepository->findCurrentActiveExam();
        if ($exam === null) {
            return new JsonResponse(['status' => 'ignored']);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $attempt = $this->attemptRepository->findByCandidateAndExam($currentUser, $exam);

        if ($attempt !== null && $attempt->isInProgress()) {
            $data = json_decode((string) $request->getContent(), true);
            $eventType = (string) ($data['event_type'] ?? 'unknown');
            $eventData = isset($data['data']) && is_array($data['data']) ? $data['data'] : null;

            $this->attemptService->recordTelemetry($attempt, $eventType, $eventData);
        }

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/saralash/test/submit', name: 'app_candidate_exam_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submit(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit_exam', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Yaroqsiz xavfsizlik tokeni (CSRF).');
        }

        $exam = $this->examRepository->findCurrentActiveExam();
        if ($exam === null) {
            return $this->redirectToRoute('app_home');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $attempt = $this->attemptRepository->findByCandidateAndExam($currentUser, $exam);

        if ($attempt !== null && $attempt->isInProgress()) {
            $this->attemptService->submitAttempt($attempt);
        }

        return $this->redirectToRoute('app_candidate_completed');
    }

    #[Route('/saralash/yakun', name: 'app_candidate_completed', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function completed(): Response
    {
        $exam = $this->examRepository->findCurrentActiveExam();
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $attempt = null;
        if ($exam !== null) {
            $attempt = $this->attemptRepository->findByCandidateAndExam($currentUser, $exam);
        }

        return $this->render('candidate/completed.html.twig', [
            'attempt' => $attempt,
            'exam' => $exam,
        ]);
    }
}
