<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class JWTDecodedListener
{
    public function __construct(private readonly UserRepository $userRepository) {}

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded')]
    public function onLexikJwtAuthenticationOnJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $jit = $payload['jit'] ?? null;

        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $payload['email']]);
        /** @var UserJwt $userJwt */
        $userJwt = $this->userRepository->getUserJit($user);

        if (!$user || $jit !== $userJwt->getJwtId()) {
            $event->markAsInvalid();
        }
    }
}