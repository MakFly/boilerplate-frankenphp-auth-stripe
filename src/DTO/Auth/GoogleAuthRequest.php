<?php
declare(strict_types=1);
namespace App\DTO\Auth;
use Symfony\Component\Validator\Constraints as Assert;

class GoogleAuthRequest
{
    /**
     * @param array<string, mixed> $googleData Les données d'authentification Google
     */
    public function __construct(
        #[Assert\NotBlank]
        public readonly array $googleData
    ) {}
}