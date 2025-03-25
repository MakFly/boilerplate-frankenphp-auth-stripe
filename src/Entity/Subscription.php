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
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    
    #[ORM\Column(length: 255)]
    private string $stripeId = '';
    
    #[ORM\Column(length: 255)]
    private string $stripePlanId = '';
    
    #[ORM\Column(length: 50)]
    private string $status = 'pending';
    
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
    
    #[ORM\Column]
    private bool $autoRenew = true;
    
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

    public function getStripePlanId(): string
    {
        return $this->stripePlanId;
    }

    public function setStripePlanId(string $stripePlanId): static
    {
        $this->stripePlanId = $stripePlanId;
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

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function setAutoRenew(bool $autoRenew): static
    {
        $this->autoRenew = $autoRenew;
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
}