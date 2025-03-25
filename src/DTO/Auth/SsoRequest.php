<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class SsoRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'email ne peut pas être vide')]
        #[Assert\Email(message: 'L\'email n\'est pas valide')]
        public readonly string $email,

        #[Assert\NotBlank(message: 'Le nom complet ne peut pas être vide')]
        #[Assert\Length(min: 2, minMessage: 'Le nom complet doit contenir au moins {{ limit }} caractères')]
        public readonly string $fullName
    ) {}
} 