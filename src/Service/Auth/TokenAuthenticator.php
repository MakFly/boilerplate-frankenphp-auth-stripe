<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Repository\SubscriptionRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TokenAuthenticator
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        #[Autowire('%env(TOKEN_TTL)%')]
        private readonly int $tokenTTL
    ) {}

    /**
     * Créer un token JWT pour un utilisateur
     *
     * @param User $user L'utilisateur pour lequel créer le token
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
     * Vérifie si un token JWT est valide
     *
     * @param string $token Le token JWT à vérifier
     * @return array{userId: string, username: string, role: string}
     * @throws \Exception Si le token est invalide
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
