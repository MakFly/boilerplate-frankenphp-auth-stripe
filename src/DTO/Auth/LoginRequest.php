<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email ne peut pas être vide')]
        #[Assert\Email(message: 'L\'email n\'est pas valide')]
        public readonly string $email,

        #[Assert\NotBlank(message: 'Le mot de passe ne peut pas être vide')]
        #[Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères')]
        public readonly string $password
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
} 