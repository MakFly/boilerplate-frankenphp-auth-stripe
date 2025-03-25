<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use App\DTO\Pattern\AbstractDTO;
use App\Entity\User;
use App\Enum\ApiMessage;
use Symfony\Component\Validator\Constraints as Assert;

final class RegisterRequest extends AbstractDTO
{
    public function __construct(
        #[Assert\NotBlank(message: ApiMessage::MISSING_FIELDS->value)]
        #[Assert\Email(message: ApiMessage::INVALID_DATA->value)]
        public readonly string $email,

        #[Assert\NotBlank(message: ApiMessage::MISSING_FIELDS->value)]
        public readonly string $password,

        #[Assert\NotBlank(message: ApiMessage::MISSING_FIELDS->value)]
        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: ApiMessage::INVALID_DATA->value,
            maxMessage: ApiMessage::INVALID_DATA->value
        )]
        public readonly string $username,
    ) {}

    public function toEntity(): User
    {
        return new User();
    }

    public static function fromEntity(object $entity): self
    {
        if (!$entity instanceof User) {
            throw new \InvalidArgumentException(sprintf('Entity must be instance of %s', User::class));
        }

        return new self(
            email: $entity->getEmail(),
            password: '',
            username: $entity->getUsername()
        );
    }

    public static function getEntityClass(): string
    {
        return User::class;
    }
} 