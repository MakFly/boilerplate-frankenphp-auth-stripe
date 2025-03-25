<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class PasswordSetupService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function enablePasswordLogin(User $user, string $plainPassword): void
    {
        if (!$user->getEmail()) {
            throw new UserNotFoundException("L'utilisateur n'a pas d'email.");
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        $user->setIsSsoLinked(true);
        $this->em->persist($user);
        $this->em->flush();
    }
}