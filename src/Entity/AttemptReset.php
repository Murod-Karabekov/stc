<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AttemptResetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttemptResetRepository::class)]
#[ORM\Table(name: 'stc_attempt_resets')]
class AttemptReset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ExamAttempt::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ExamAttempt $attempt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $adminUser = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $resetAt;

    public function __construct()
    {
        $this->resetAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
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

    public function getAdminUser(): ?User
    {
        return $this->adminUser;
    }

    public function setAdminUser(?User $adminUser): static
    {
        $this->adminUser = $adminUser;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getResetAt(): \DateTimeImmutable
    {
        return $this->resetAt;
    }
}
