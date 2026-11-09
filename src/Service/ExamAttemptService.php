<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AttemptAnswer;
use App\Entity\AttemptEvent;
use App\Entity\Exam;
use App\Entity\ExamAttempt;
use App\Entity\Question;
use App\Entity\User;
use App\Repository\AttemptAnswerRepository;
use App\Repository\ExamAttemptRepository;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class ExamAttemptService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExamAttemptRepository $attemptRepository,
        private readonly QuestionRepository $questionRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Idempotently starts or resumes an attempt for a candidate.
     */
    public function startOrResumeAttempt(User $candidate, Exam $exam): ExamAttempt
    {
        $existing = $this->attemptRepository->findByCandidateAndExam($candidate, $exam);
        if ($existing !== null) {
            // Check if deadline passed while away
            $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone('Asia/Tashkent'));
            if ($existing->isInProgress() && $now > $existing->getDeadlineAt()) {
                $this->finalizeExpiredAttempt($existing);
            }
            return $existing;
        }

        if (!$exam->isOpen()) {
            throw new \DomainException('Ushbu imtihonga yangi urinishlar qabuli to\'xtatilgan.');
        }

        // Fetch all questions for this exam
        $questions = $this->questionRepository->findBy(['exam' => $exam]);
        if (empty($questions)) {
            throw new \DomainException('Ushbu imtihonda savollar mavjud emas.');
        }

        // Group questions by section
        $groupedBySection = [];
        foreach ($questions as $q) {
            $sectionId = $q->getSection() ? $q->getSection()->getId() : 0;
            $groupedBySection[$sectionId][] = $q;
        }

        // Shuffle questions within each section
        $orderedQuestions = [];
        foreach ($groupedBySection as $secQuestions) {
            shuffle($secQuestions);
            foreach ($secQuestions as $q) {
                $orderedQuestions[] = $q;
            }
        }

        // Build question snapshot with shuffled options
        $snapshot = [];
        $totalMaxScore = 0;

        foreach ($orderedQuestions as $displayIndex => $q) {
            $totalMaxScore += $q->getPoints();

            $optionsList = [];
            $options = $q->getOptions()->toArray();
            shuffle($options);

            foreach ($options as $optIndex => $opt) {
                // Strictly omit isCorrect from candidate snapshot
                $optionsList[] = [
                    'id' => $opt->getId(),
                    'content' => $opt->getContent(),
                    'position' => $optIndex + 1,
                ];
            }

            $snapshot[] = [
                'question_id' => $q->getId(),
                'display_number' => $displayIndex + 1,
                'section_id' => $q->getSection() ? $q->getSection()->getId() : null,
                'section_title' => $q->getSection() ? $q->getSection()->getTitle() : 'Umumiy',
                'section_position' => $q->getSection() ? $q->getSection()->getPosition() : 1,
                'content' => $q->getContent(),
                'image_path' => $q->getImagePath(),
                'image_alt' => $q->getImageAlt(),
                'image_version' => $q->getImageVersion(),
                'image_source_type' => $q->getImageSourceType() ?? 'upload',
                'points' => $q->getPoints(),
                'options' => $optionsList,
            ];
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone('Asia/Tashkent'));
        $deadline = $now->modify(sprintf('+%d minutes', $exam->getDurationMinutes()));

        $attempt = new ExamAttempt();
        $attempt->setCandidate($candidate);
        $attempt->setExam($exam);
        $attempt->setStartedAt($now);
        $attempt->setDeadlineAt($deadline);
        $attempt->setMaxScore($totalMaxScore);
        $attempt->setQuestionSnapshot($snapshot);
        $attempt->setStatus(ExamAttempt::STATUS_IN_PROGRESS);

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        return $attempt;
    }

    /**
     * Atomically saves or updates an answer for an in-progress attempt.
     *
     * @throws \DomainException       attempt finished or expired
     * @throws \RuntimeException      stale revision (another tab wrote more recently) → 409
     * @throws \InvalidArgumentException question/option id not in snapshot
     */
    public function saveAnswer(ExamAttempt $attempt, int $questionId, ?int $optionId, int $expectedRevision): array
    {
        if (!$attempt->isInProgress()) {
            throw new \DomainException('Ushbu urinish yakunlangan. Javoblar qabul qilinmaydi.');
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone('Asia/Tashkent'));
        if ($now > $attempt->getDeadlineAt()) {
            $this->finalizeExpiredAttempt($attempt);
            throw new \DomainException('Imtihon vaqti tugadi. Yangi javoblar saqlanmaydi.');
        }

        // Reject stale writes from a second tab: client must know the current revision.
        // expectedRevision = 0 means first save (revision not yet tracked by client).
        if ($expectedRevision > 0 && $attempt->getRevision() !== $expectedRevision) {
            throw new \RuntimeException('Ikki oyna ziddiyati aniqlandi. Sahifani yangilang.');
        }

        // Validate question belongs to attempt snapshot
        $validQuestion = false;
        $validOption = false;

        foreach ($attempt->getQuestionSnapshot() as $qSnapshot) {
            if ($qSnapshot['question_id'] === $questionId) {
                $validQuestion = true;
                if ($optionId === null) {
                    $validOption = true;
                } else {
                    foreach ($qSnapshot['options'] as $opt) {
                        if ($opt['id'] === $optionId) {
                            $validOption = true;
                            break;
                        }
                    }
                }
                break;
            }
        }

        if (!$validQuestion) {
            throw new \InvalidArgumentException('Savol identifikatori noto\'g\'ri.');
        }

        if (!$validOption) {
            throw new \InvalidArgumentException('Tanlangan javob varianti yaroqsiz.');
        }

        $answerRepo = $this->entityManager->getRepository(AttemptAnswer::class);
        $answer = $answerRepo->findOneBy([
            'attempt' => $attempt,
            'questionId' => $questionId,
        ]);

        if ($answer === null) {
            $answer = new AttemptAnswer();
            $answer->setAttempt($attempt);
            $answer->setQuestionId($questionId);
            $this->entityManager->persist($answer);
        }

        $answer->setSelectedOptionId($optionId);
        $attempt->incrementRevision();

        $this->entityManager->flush();

        return [
            'status' => 'saved',
            'question_id' => $questionId,
            'selected_option_id' => $optionId,
            'server_revision' => $attempt->getRevision(),
            'saved_at' => $answer->getAnsweredAt()->format('H:i:s'),
        ];
    }

    /**
     * Submits an exam attempt voluntarily by the candidate.
     */
    public function submitAttempt(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->isSubmitted()) {
            return $attempt; // Idempotent
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone('Asia/Tashkent'));
        if ($now > $attempt->getDeadlineAt()) {
            return $this->finalizeExpiredAttempt($attempt);
        }

        $attempt->setStatus(ExamAttempt::STATUS_SUBMITTED);
        $attempt->setSubmittedAt($now);

        $this->calculateScores($attempt);

        $this->entityManager->flush();

        return $attempt;
    }

    /**
     * Finalizes an expired exam attempt (invoked on deadline breach or cron command).
     */
    public function finalizeExpiredAttempt(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->isSubmitted() || $attempt->isExpired()) {
            return $attempt;
        }

        $attempt->setStatus(ExamAttempt::STATUS_EXPIRED);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone('Asia/Tashkent'));
        $attempt->setSubmittedAt($now);

        $this->calculateScores($attempt);

        $this->entityManager->flush();

        return $attempt;
    }

    /**
     * Calculates attempt total and per-section scores server-side.
     */
    private function calculateScores(ExamAttempt $attempt): void
    {
        $exam = $attempt->getExam();
        $questions = $this->questionRepository->findBy(['exam' => $exam]);
        $questionsById = [];
        foreach ($questions as $q) {
            $questionsById[$q->getId()] = $q;
        }

        $answerRepo = $this->entityManager->getRepository(AttemptAnswer::class);
        $answers = $answerRepo->findBy(['attempt' => $attempt]);
        $answersByQuestionId = [];
        foreach ($answers as $ans) {
            $answersByQuestionId[$ans->getQuestionId()] = $ans;
        }

        $totalScore = 0;
        $sectionScores = [];

        foreach ($questionsById as $qId => $q) {
            $sectionPos = $q->getSection() ? (string) $q->getSection()->getPosition() : '1';
            if (!isset($sectionScores[$sectionPos])) {
                $sectionScores[$sectionPos] = 0;
            }

            $ans = $answersByQuestionId[$qId] ?? null;
            $correctOpt = $q->getCorrectOption();

            if ($ans !== null && $correctOpt !== null && $ans->getSelectedOptionId() === $correctOpt->getId()) {
                $ans->setIsCorrect(true);
                $ans->setPointsAwarded($q->getPoints());
                $totalScore += $q->getPoints();
                $sectionScores[$sectionPos] += $q->getPoints();
            } else {
                if ($ans !== null) {
                    $ans->setIsCorrect(false);
                    $ans->setPointsAwarded(0);
                }
            }
        }

        $attempt->setTotalScore($totalScore);
        $attempt->setSectionScores($sectionScores);
    }

    /**
     * Records telemetry event with rate limiting.
     */
    public function recordTelemetry(ExamAttempt $attempt, string $eventType, ?array $eventData): void
    {
        // Limit total events per attempt to 100 to avoid excessive storage
        if ($attempt->getEvents()->count() > 100) {
            return;
        }

        $event = new AttemptEvent();
        $event->setAttempt($attempt);
        $event->setEventType(substr($eventType, 0, 60));
        $event->setEventData($eventData);

        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }
}
