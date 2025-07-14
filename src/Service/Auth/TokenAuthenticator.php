<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Interface\Auth\TokenAuthenticatorInterface;
use App\Repository\SubscriptionRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TokenAuthenticator implements TokenAuthenticatorInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        #[Autowire('%env(TOKEN_TTL)%')]
        private readonly int $tokenTTL
    ) {}

    /**
     * Create a JWT token for a user
     *
     * @param User $user The user for whom to create the token
     * @return array{userId: string, token: string, token_expires_in: int}
     */
    public function createAuthenticationToken(User $user): array
    {
        $token = $this->jwtTokenManager->create($user);

        return [
            'userId' => $user->getId(),
            'username' => $user->getUsername(),
            'token' => $token,
            'token_expires_in' => $this->tokenTTL,
            'isSsoLinked' => $user->getIsSsoLinked(),
            'role' => in_array('ROLE_ADMIN', $user->getRoles()) ? 'admin' : 'user'
        ];
    }

    /**
     * Verifies if a JWT token is valid
     *
     * @param string $token The JWT token to verify
     * @return array{userId: string, username: string, role: string}
     * @throws \Exception If the token is invalid
     */
    public function verifyJwt(string $token): array
    {
        try {
            $payload = $this->jwtTokenManager->parse($token);
            return $payload;
        } catch (\Exception $e) {
            throw new \Exception('Invalid JWT token');
        }
    }
}
