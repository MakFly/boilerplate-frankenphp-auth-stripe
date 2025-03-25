<?php
declare(strict_types=1);
namespace App\Entity;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidTrait;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: '`user_jit`')]
#[ORM\HasLifecycleCallbacks]
class UserJit
{
    use UuidTrait;
    use TimestampableTrait;
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;
    /**
     * @var string|null The JWT ID : jit
     */
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $jwtId = null;
    
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
    }
    
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getJwtId(): ?string
    {
        return $this->jwtId;
    }

    public function setJwtId(?string $jwtId): static
    {
        $this->jwtId = $jwtId;
        return $this;
    }
}