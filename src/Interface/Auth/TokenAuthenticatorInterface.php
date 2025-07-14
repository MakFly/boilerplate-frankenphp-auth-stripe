<?php

declare(strict_types=1);

namespace App\Interface\Auth;

use App\Entity\User;

interface TokenAuthenticatorInterface
{
    /**
     * Create an authentication token for a user
     */
    public function createAuthenticationToken(User $user): array;

    /**
     * Verify a JWT token and return user data
     */
    public function verifyJwt(string $token): array;
}