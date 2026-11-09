<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExamAttemptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExamAttemptRepository::class)]
#[ORM\Table(name: 'stc_exam_attempts')]
#[ORM\UniqueConstraint(name: 'uniq_candidate_exam_attempt', columns: ['candidate_id', 'exam_id'])]
class ExamAttempt
{
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public const DECISION_PENDING = 'pending';
    public const DECISION_ADVANCED = 'advanced';
    public const DECISION_RESERVE = 'reserve';
    public const DECISION_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $candidate = null;

    #[ORM\ManyToOne(targetEntity: Exam::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Exam $exam = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $candidateCode = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_IN_PROGRESS])]
    private string $status = self::STATUS_IN_PROGRESS;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $deadlineAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $totalScore = 0;

    #[ORM\Column(options: ['default' => 25])]
    private int $maxScore = 25;

    /**
     * @var array<string, int>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $sectionScores = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $questionSnapshot = [];

    #[ORM\Column(options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $selectionDecision = self::DECISION_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decisionChangedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $decisionChangedBy = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSyncedToSheets = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $syncedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, AttemptAnswer>
     */
    #[ORM\OneToMany(targetEntity: AttemptAnswer::class, mappedBy: 'attempt', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $answers;

    /**
     * @var Collection<int, AttemptEvent>
     */
    #[ORM\OneToMany(targetEntity: AttemptEvent::class, mappedBy: 'attempt', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $events;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
        $this->candidateCode = 'STC-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCandidate(): ?User
    {
        return $this->candidate;
    }

    public function setCandidate(?User $candidate): static
    {
        $this->candidate = $candidate;

        return $this;
    }

    public function getExam(): ?Exam
    {
        return $this->exam;
    }

    public function setExam(?Exam $exam): static
    {
        $this->exam = $exam;

        return $this;
    }

    public function getCandidateCode(): ?string
    {
        return $this->candidateCode;
    }

    public function setCandidateCode(string $candidateCode): static
    {
        $this->candidateCode = $candidateCode;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));

        return $this;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getDeadlineAt(): \DateTimeImmutable
    {
        return $this->deadlineAt;
    }

    public function setDeadlineAt(\DateTimeImmutable $deadlineAt): static
    {
        $this->deadlineAt = $deadlineAt;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));

        return $this;
    }

    public function getTotalScore(): int
    {
        return $this->totalScore;
    }

    public function setTotalScore(int $totalScore): static
    {
        $this->totalScore = $totalScore;

        return $this;
    }

    public function getMaxScore(): int
    {
        return $this->maxScore;
    }

    public function setMaxScore(int $maxScore): static
    {
        $this->maxScore = $maxScore;

        return $this;
    }

    /**
     * @return array<string, int>
     */
    public function getSectionScores(): array
    {
        return $this->sectionScores;
    }

    /**
     * @param array<string, int> $sectionScores
     */
    public function setSectionScores(array $sectionScores): static
    {
        $this->sectionScores = $sectionScores;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getQuestionSnapshot(): array
    {
        return $this->questionSnapshot;
    }

    /**
     * @param array<int, array<string, mixed>> $questionSnapshot
     */
    public function setQuestionSnapshot(array $questionSnapshot): static
    {
        $this->questionSnapshot = $questionSnapshot;

        return $this;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function incrementRevision(): static
    {
        $this->revision++;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));

        return $this;
    }

    public function getSelectionDecision(): string
    {
        return $this->selectionDecision;
    }

    public function setSelectionDecision(string $decision, string $changedBy): static
    {
        $this->selectionDecision = $decision;
        $this->decisionChangedAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
        $this->decisionChangedBy = $changedBy;

        return $this;
    }

    public function getDecisionChangedAt(): ?\DateTimeImmutable
    {
        return $this->decisionChangedAt;
    }

    public function getDecisionChangedBy(): ?string
    {
        return $this->decisionChangedBy;
    }

    public function isSyncedToSheets(): bool
    {
        return $this->isSyncedToSheets;
    }

    public function setIsSyncedToSheets(bool $isSyncedToSheets): static
    {
        $this->isSyncedToSheets = $isSyncedToSheets;

        return $this;
    }

    public function getSyncedAt(): ?\DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?\DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, AttemptAnswer>
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(AttemptAnswer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setAttempt($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, AttemptEvent>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(AttemptEvent $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setAttempt($this);
        }

        return $this;
    }
}
