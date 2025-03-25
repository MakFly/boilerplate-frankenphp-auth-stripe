<?php
declare(strict_types=1);
namespace App\Entity;
use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    
    #[ORM\Column(length: 255)]
    private string $stripeId = '';
    
    #[ORM\Column(length: 50)]
    private string $status = 'pending';
    
    #[ORM\Column]
    private int $amount = 0;
    
    #[ORM\Column(length: 3)]
    private string $currency = 'eur';
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;
    
    #[ORM\Column(length: 50)]
    private string $paymentType = 'one_time';
    
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User|null $user = null;
    
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;
    
    #[ORM\OneToOne(mappedBy: 'payment', cascade: ['persist'])]
    private ?Invoice $invoice = null;
    
    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPaymentType(): string
    {
        return $this->paymentType;
    }

    public function setPaymentType(string $paymentType): static
    {
        $this->paymentType = $paymentType;
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
        // Set the owning side of the relation if necessary
        if ($invoice !== null && $invoice->getPayment() !== $this) {
            $invoice->setPayment($this);
        }

        $this->invoice = $invoice;
        return $this;
    }
}