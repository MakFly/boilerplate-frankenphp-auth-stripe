<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\Attribute\Groups;

trait UuidTrait
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['product:read'])]
    private ?Uuid $id = null;

    public function __construct()
    {
        $this->id = Uuid::v6();
    }

    public function getId(): ?string
    {
        return $this->id?->toRfc4122();
    }

    public function setId(Uuid $uuid): self
    {
        $this->id = $uuid;

        return $this;
    }
}
