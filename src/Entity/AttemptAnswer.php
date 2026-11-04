<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AttemptAnswerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttemptAnswerRepository::class)]
#[ORM\Table(name: 'stc_attempt_answers')]
#[ORM\UniqueConstraint(name: 'uniq_attempt_question_answer', columns: ['attempt_id', 'question_id'])]
class AttemptAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ExamAttempt::class, inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ExamAttempt $attempt = null;

    #[ORM\Column]
    private ?int $questionId = null;

    #[ORM\Column(nullable: true)]
    private ?int $selectedOptionId = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isCorrect = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $pointsAwarded = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $answeredAt;

    public function __construct()
    {
        $this->answeredAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttempt(): ?ExamAttempt
    {
        return $this->attempt;
    }

    public function setAttempt(?ExamAttempt $attempt): static
    {
        $this->attempt = $attempt;

        return $this;
    }

    public function getQuestionId(): ?int
    {
        return $this->questionId;
    }

    public function setQuestionId(int $questionId): static
    {
        $this->questionId = $questionId;

        return $this;
    }

    public function getSelectedOptionId(): ?int
    {
        return $this->selectedOptionId;
    }

    public function setSelectedOptionId(?int $selectedOptionId): static
    {
        $this->selectedOptionId = $selectedOptionId;
        $this->answeredAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));

        return $this;
    }

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function setIsCorrect(bool $isCorrect): static
    {
        $this->isCorrect = $isCorrect;

        return $this;
    }

    public function getPointsAwarded(): int
    {
        return $this->pointsAwarded;
    }

    public function setPointsAwarded(int $pointsAwarded): static
    {
        $this->pointsAwarded = $pointsAwarded;

        return $this;
    }

    public function getAnsweredAt(): \DateTimeImmutable
    {
        return $this->answeredAt;
    }
}
