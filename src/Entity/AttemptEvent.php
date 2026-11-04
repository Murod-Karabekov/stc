<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AttemptEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttemptEventRepository::class)]
#[ORM\Table(name: 'stc_attempt_events')]
class AttemptEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ExamAttempt::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ExamAttempt $attempt = null;

    #[ORM\Column(length: 64)]
    private ?string $eventType = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $eventData = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
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

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEventData(): ?array
    {
        return $this->eventData;
    }

    /**
     * @param array<string, mixed>|null $eventData
     */
    public function setEventData(?array $eventData): static
    {
        $this->eventData = $eventData;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
