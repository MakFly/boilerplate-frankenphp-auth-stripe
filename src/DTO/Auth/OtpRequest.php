<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class OtpRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        private readonly string $email,

        #[Assert\NotBlank(message: 'OTP code is required')]
        #[Assert\Length(exactly: 6, exactMessage: 'OTP must be exactly 6 characters')]
        #[Assert\Regex(pattern: '/^\d+$/', message: 'OTP must contain only digits')]
        private readonly int $code
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCode(): int
    {
        return $this->code;
    }
}
