<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StripeWebhookLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StripeWebhookLogRepository::class)]
#[ORM\Table(name: 'stripe_webhook_logs')]
#[ORM\Index(columns: ['event_id'], name: 'stripe_webhook_event_idx')]
#[ORM\Index(columns: ['created_at'], name: 'stripe_webhook_created_idx')]
class StripeWebhookLog
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_IGNORED = 'ignored';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $eventType;

    #[ORM\Column(length: 255, unique: true)]
    private string $eventId;

    #[ORM\Column(type: 'json')]
    /** @var array<string, mixed> */
    private array $payload = [];

    #[ORM\Column(length: 50)]
    private string $status;

    #[ORM\Column(length: 50)]
    private string $processorType;

    #[ORM\Column(nullable: true)]
    private ?string $relatedObjectId = null;

    #[ORM\Column(nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    /** @var array<string, mixed>|null */
    private ?array $errorDetails = null;

    #[ORM\Column]
    private int $retryCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct(
        string $eventType = '',
        string $eventId = '',
        string $processorType = ''
    ) {
        $this->id = Uuid::v4();
        $this->eventType = $eventType;
        $this->eventId = $eventId;
        $this->processorType = $processorType;
        $this->status = self::STATUS_PROCESSING;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function setEventId(string $eventId): static
    {
        $this->eventId = $eventId;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        if ($status === self::STATUS_SUCCESS || $status === self::STATUS_ERROR) {
            $this->processedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getProcessorType(): string
    {
        return $this->processorType;
    }

    public function setProcessorType(string $processorType): static
    {
        $this->processorType = $processorType;
        return $this;
    }

    public function getRelatedObjectId(): ?string
    {
        return $this->relatedObjectId;
    }

    public function setRelatedObjectId(?string $relatedObjectId): static
    {
        $this->relatedObjectId = $relatedObjectId;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getErrorDetails(): ?array
    {
        return $this->errorDetails;
    }

    /** @param array<string, mixed>|null $errorDetails */
    public function setErrorDetails(?array $errorDetails): static
    {
        $this->errorDetails = $errorDetails;
        return $this;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): static
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    public function incrementRetryCount(): static
    {
        $this->retryCount++;
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

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;
        return $this;
    }

    /**
     * Marque le webhook comme traité avec succès
     */
    public function markAsSuccess(?string $relatedObjectId = null): static
    {
        $this->status = self::STATUS_SUCCESS;
        $this->processedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        
        if ($relatedObjectId) {
            $this->relatedObjectId = $relatedObjectId;
        }
        
        return $this;
    }

    /**
     * Marque le webhook comme échoué
     * @param array<string, mixed>|null $errorDetails
     */
    public function markAsError(string $errorMessage, ?array $errorDetails = null): static
    {
        $this->status = self::STATUS_ERROR;
        $this->errorMessage = $errorMessage;
        $this->errorDetails = $errorDetails;
        $this->processedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        
        return $this;
    }

    /**
     * Marque le webhook comme ignoré
     */
    public function markAsIgnored(string $reason): static
    {
        $this->status = self::STATUS_IGNORED;
        $this->errorMessage = $reason;
        $this->processedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        
        return $this;
    }
} 