<?php
declare(strict_types=1);
namespace App\Entity;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    // Constantes de statut
    public const STATUS_PENDING = 'pending';        // En attente, paiement non confirmé
    public const STATUS_ACTIVE = 'active';          // Abonnement actif
    public const STATUS_CANCELED = 'canceled';      // Abonnement annulé
    public const STATUS_INCOMPLETE = 'incomplete';  // Processus de paiement incomplet
    public const STATUS_PAST_DUE = 'past_due';      // Paiement en retard
    public const STATUS_UNPAID = 'unpaid';          // Paiement échoué
    public const STATUS_TRIALING = 'trialing';      // Période d'essai

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    
    #[ORM\Column(length: 255)]
    private string $stripeId = '';
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSubscriptionId = null;
    
    #[ORM\Column(length: 255)]
    private string $stripePlanId = '';
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeProductId = null;
    
    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;
    
    #[ORM\Column]
    private int $amount = 0;
    
    #[ORM\Column(length: 3)]
    private string $currency = 'eur';
    
    #[ORM\Column(length: 50)]
    private string $interval = 'month';
    
    #[ORM\Column]
    private \DateTimeImmutable $startDate;
    
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endDate = null;
    
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $canceledAt = null;
    
    #[ORM\Column]
    private bool $autoRenew = true;
    
    #[ORM\Column(type: 'json', nullable: true)]
    /** @var array<string, mixed>|null */
    private ?array $metadata = null;
    
    #[ORM\Column]
    private int $retryCount = 0;
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastErrorMessage = null;
    
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User|null $user = null;
    
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;
    
    #[ORM\OneToOne(mappedBy: 'subscription', cascade: ['persist'])]
    private ?Invoice $invoice = null;
    
    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->startDate = new \DateTimeImmutable();
    }
    
    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getStripeId(): string
    {
        return $this->stripeId;
    }

    public function setStripeId(string $stripeId): static
    {
        $this->stripeId = $stripeId;
        return $this;
    }
    
    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }
    
    public function setStripeSubscriptionId(?string $stripeSubscriptionId): static
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        return $this;
    }

    public function getStripePlanId(): string
    {
        return $this->stripePlanId;
    }

    public function setStripePlanId(string $stripePlanId): static
    {
        $this->stripePlanId = $stripePlanId;
        return $this;
    }
    
    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }
    
    public function setStripeProductId(?string $stripeProductId): static
    {
        $this->stripeProductId = $stripeProductId;
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
        
        // Si l'abonnement est annulé, on enregistre la date d'annulation
        if ($status === self::STATUS_CANCELED && $this->canceledAt === null) {
            $this->canceledAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function setInterval(string $interval): static
    {
        $this->interval = $interval;
        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }
    
    public function getCanceledAt(): ?\DateTimeImmutable
    {
        return $this->canceledAt;
    }
    
    public function setCanceledAt(?\DateTimeImmutable $canceledAt): static
    {
        $this->canceledAt = $canceledAt;
        return $this;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function setAutoRenew(bool $autoRenew): static
    {
        $this->autoRenew = $autoRenew;
        return $this;
    }
    
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
    
    /** @param array<string, mixed>|null $metadata */
    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
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
    
    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }
    
    public function setLastErrorMessage(?string $lastErrorMessage): static
    {
        $this->lastErrorMessage = $lastErrorMessage;
        return $this;
    }

    public function getUser(): User|null
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
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
    
    public function updateTimestamps(): static
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        // set the owning side of the relation if necessary
        if ($invoice !== null && $invoice->getSubscription() !== $this) {
            $invoice->setSubscription($this);
        }

        $this->invoice = $invoice;
        return $this;
    }
    
    /**
     * Indique si l'abonnement est actif
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
    
    /**
     * Indique si l'abonnement est en attente de paiement
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
    
    /**
     * Indique si l'abonnement est annulé
     */
    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }
}