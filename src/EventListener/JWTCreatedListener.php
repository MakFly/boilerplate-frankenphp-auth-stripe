<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\UserJit;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final class JWTCreatedListener
{

    public function __construct(private EntityManagerInterface $entityManager, private RequestStack $requestStack) {}

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
    public function onLexikJwtAuthenticationOnJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $jwtId = Uuid::v6()->toRfc4122();

        $userJit = $this->entityManager->getRepository(UserJit::class)->findOneBy([
            'user' => $user
        ]);

        if ($userJit instanceof UserJit) {
            $userJit->setJwtId($jwtId);
        } else {
            $userJit = new UserJit();
            $userJit->setUser($user);
            $userJit->setJwtId($jwtId);
        }

        $this->entityManager->persist($userJit);
        $this->entityManager->flush();

        $event->setData(array_merge($event->getData(), ['jit' => $jwtId]));
    }
}