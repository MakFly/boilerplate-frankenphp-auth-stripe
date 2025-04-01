<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Interface\NotifierInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class ResetPasswordService
{
    private const TOKEN_EXPIRATION_DELAY = 30; // minutes

    public function __construct(
        private readonly NotifierInterface $notifier,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * @param string $email
     * @return bool
     */
    public function requestPasswordReset(string $email): bool
    {
        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $email]);
        
        if (!$user) {
            return false;
        }

        if ($user->getResetPasswordToken() && $user->getResetPasswordTokenExpiresAt() > new \DateTime('now', new \DateTimeZone('UTC'))) {
            return false;
        }

        try {
            $token = $this->tokenGenerator->generateToken();
            $user->setResetPasswordToken($token);
            $user->setResetPasswordTokenExpiresAt(new \DateTime(sprintf('+%d minutes', self::TOKEN_EXPIRATION_DELAY), new \DateTimeZone('Europe/Paris')));

            $resetLink = $this->urlGenerator->generate('app_reset_password', [
                'token' => $token
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->notifier->send(
                $user->getEmail(),
                'Réinitialisation de votre mot de passe',
                [
                    'reset_link' => $resetLink,
                    'expiration_delay' => self::TOKEN_EXPIRATION_DELAY
                ],
                ['template' => 'emails/reset_password.html.twig']
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $token
     * @param string $newPassword
     * @return bool
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = $this->userRepository->findOneBy(['resetPasswordToken' => $token]);

        if (!$user || !$this->isTokenValid($user)) {
            return false;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setResetPasswordToken(null);
        $user->setResetPasswordTokenExpiresAt(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return true;
    }

    public function checkToken(string $token): bool
    {
        $user = $this->userRepository->findOneBy(['resetPasswordToken' => $token]);

        return $this->isTokenValid($user);
    }

    /**
     * @param User $user
     * @return bool
     */
    private function isTokenValid(User $user): bool
    {
        $expiresAt = $user->getResetPasswordTokenExpiresAt();
        if (!$expiresAt) {
            return false;
        }

        return $expiresAt > new \DateTime('now', new \DateTimeZone('Europe/Paris'));
    }
}