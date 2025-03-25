<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidTrait;
use App\Enum\AuthProvider;
use App\Repository\UserRepository;
use DateTimeInterface;
use DateTimeImmutable;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`users`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UuidTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 180)]
    #[Groups(['user:read'])]
    private ?string $username = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read'])]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    /** @var list<string> */
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(nullable: true, length: 6)]
    private ?string $otp = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $otpExpiration = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['user:read'])]
    /** @var list<string> */
    private array $provider = [AuthProvider::CREDENTIALS];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetPasswordToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $resetPasswordTokenExpiresAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $lastLogin = null;

    /**
     * Nombre d'échecs de connexion.
     */
    #[ORM\Column(type: 'integer')]
    private int $failedLoginAttempts = 0;

    /**
     * Date et heure jusqu'à laquelle le compte est verrouillé.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $lockedUntil = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isSsoLinked = false;

    #[ORM\Column(type: 'json')]
    private array $linkedAccounts = [];

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $googleId = null;

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password ?? '';
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getOtp(): ?string
    {
        return $this->otp;
    }

    public function setOtp(?string $otp): static
    {
        $this->otp = $otp;
        return $this;
    }

    public function getOtpExpiration(): ?DateTimeInterface
    {
        return $this->otpExpiration;
    }

    public function setOtpExpiration(?DateTimeInterface $otpExpiration): static
    {
        $this->otpExpiration = $otpExpiration;
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getProvider(): array
    {
        return array_values(array_unique($this->provider));
    }

    /**
     * @param list<string> $provider
     */
    public function setProvider(array $provider): static
    {
        $this->provider = array_values(array_unique($provider));
        return $this;
    }

    public function addProvider(string $provider): static
    {
        if (!in_array($provider, $this->provider, true)) {
            $this->provider[] = $provider;
        }
        return $this;
    }

    public function removeProvider(string $provider): static
    {
        $this->provider = array_values(array_diff($this->provider, [$provider]));
        return $this;
    }

    public function hasProvider(string $provider): bool
    {
        return in_array($provider, $this->provider, true);
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $resetPasswordToken): static
    {
        $this->resetPasswordToken = $resetPasswordToken;
        return $this;
    }

    public function getResetPasswordTokenExpiresAt(): ?DateTimeInterface
    {
        return $this->resetPasswordTokenExpiresAt;
    }

    public function setResetPasswordTokenExpiresAt(?DateTimeInterface $resetPasswordTokenExpiresAt): static
    {
        $this->resetPasswordTokenExpiresAt = $resetPasswordTokenExpiresAt;
        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): static
    {
        $this->stripeCustomerId = $stripeCustomerId;
        return $this;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->stripeCustomerId !== null;
    }

    public function getLastLogin(): ?DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function setFailedLoginAttempts(int $failedLoginAttempts): static
    {
        $this->failedLoginAttempts = $failedLoginAttempts;
        return $this;
    }

    public function incrementFailedLoginAttempts(): static
    {
        $this->failedLoginAttempts++;
        return $this;
    }

    public function resetFailedLoginAttempts(): static
    {
        $this->failedLoginAttempts = 0;
        return $this;
    }

    public function getLockedUntil(): ?DateTimeInterface
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?DateTimeInterface $lockedUntil): static
    {
        $this->lockedUntil = $lockedUntil;
        return $this;
    }

    public function isLocked(): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > new DateTime();
    }

    public function getIsSsoLinked(): bool
    {
        return $this->isSsoLinked;
    }

    public function setIsSsoLinked(bool $isSsoLinked): static
    {
        $this->isSsoLinked = $isSsoLinked;
        return $this;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getLinkedAccounts(): array
    {
        return $this->linkedAccounts;
    }

    public function addLinkedAccount(string $provider, string $externalId): static
    {
        if (!isset($this->linkedAccounts[$provider])) {
            $this->linkedAccounts[$provider] = [];
        }
        if (!in_array($externalId, $this->linkedAccounts[$provider], true)) {
            $this->linkedAccounts[$provider][] = $externalId;
        }
        return $this;
    }

    public function removeLinkedAccount(string $provider): static
    {
        unset($this->linkedAccounts[$provider]);
        return $this;
    }

    public function hasLinkedAccount(string $provider): bool
    {
        return isset($this->linkedAccounts[$provider]) && !empty($this->linkedAccounts[$provider]);
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }
}
