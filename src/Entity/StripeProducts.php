<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidTrait;
use App\Repository\StripeProductsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: StripeProductsRepository::class)]
class StripeProducts
{
    use UuidTrait, TimestampableTrait;

    #[ORM\Column(length: 50)]
    #[Groups(['public'])]
    private string $planId;

    #[ORM\Column(length: 255)]
    #[Groups(['public'])]
    private string $name;

    #[ORM\Column(type: 'text')]
    #[Groups(['public'])]
    private string $description;

    #[ORM\Column]
    #[Groups(['public'])]
    private float $monthlyPrice;

    #[ORM\Column]
    #[Groups(['public'])]
    private float $annualPrice;

    #[ORM\Column(type: 'json')]
    #[Groups(['public'])]
    /** @var array<string> $features */
    private array $features = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeMonthlyPriceId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeAnnualPriceId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeProductId = null;

    public function __construct(
        string $planId = '',
        string $name = '',
        string $description = '',
        float $monthlyPrice = 0.0,
        float $annualPrice = 0.0
    ) {
        $this->planId = $planId;
        $this->name = $name;
        $this->description = $description;
        $this->monthlyPrice = $monthlyPrice;
        $this->annualPrice = $annualPrice;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
    }

    public function getPlanId(): string
    {
        return $this->planId;
    }

    public function setPlanId(string $planId): self
    {
        $this->planId = $planId;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getMonthlyPrice(): float
    {
        return $this->monthlyPrice;
    }

    public function setMonthlyPrice(float $monthlyPrice): self
    {
        $this->monthlyPrice = $monthlyPrice;
        return $this;
    }

    public function getAnnualPrice(): float
    {
        return $this->annualPrice;
    }

    public function setAnnualPrice(float $annualPrice): self
    {
        $this->annualPrice = $annualPrice;
        return $this;
    }

    /** @return array<string> $features */
    public function getFeatures(): array
    {
        return $this->features;
    }

    /** @param array<string> $features */
    public function setFeatures(array $features): self
    {
        $this->features = $features;
        return $this;
    }

    public function getStripeMonthlyPriceId(): ?string
    {
        return $this->stripeMonthlyPriceId;
    }

    public function setStripeMonthlyPriceId(?string $stripeMonthlyPriceId): self
    {
        $this->stripeMonthlyPriceId = $stripeMonthlyPriceId;
        return $this;
    }

    public function getStripeAnnualPriceId(): ?string
    {
        return $this->stripeAnnualPriceId;
    }

    public function setStripeAnnualPriceId(?string $stripeAnnualPriceId): self
    {
        $this->stripeAnnualPriceId = $stripeAnnualPriceId;
        return $this;
    }

    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }

    public function setStripeProductId(?string $stripeProductId): self
    {
        $this->stripeProductId = $stripeProductId;
        return $this;
    }
}