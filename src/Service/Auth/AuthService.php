<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\DTO\Auth\LoginRequest;
use App\Entity\User;
use App\Enum\ApiMessage;
use App\Enum\AuthProvider;
use App\Exception\Auth\AuthenticationFailedException;
use App\Exception\Auth\RegistrationException;
use App\Interface\AuthInterface;
use App\Interface\NotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

final class AuthService extends AuthOptionsService implements AuthInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly NotifierInterface $notifier,
        private readonly TokenAuthenticator $tokenAuthenticator,
        RequestStack $requestStack,
    ) {
        parent::__construct($requestStack);
    }

    /**
     * @param LoginRequest $loginRequest
     * @return array|bool
     */
    public function authCustom(LoginRequest $loginRequest): array|bool
    {
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $loginRequest->getEmail()]);

        if (!$user) {
            throw new UserNotFoundException(ApiMessage::USER_NOT_FOUND->value);
        }

        if ($this->isOTPEnabled()) {

            /**
             * TODO: vérifie si un OTP a déjà été envoyé à l'utilisateur
             * si oui, on ne le renvoie pas et on renvoie un message d'erreur comme "Un OTP a déjà été envoyé à l'utilisateur" et on renvoie un code 400
             * si non, on envoie un OTP et on renvoie un message de succès
             */


            $otp = rand(100000, 999999);
            $user->setOTP(strval($otp));
            $user->setOtpExpiration(new \DateTime('+5 minutes', new \DateTimeZone('UTC')));
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // send notification email
            $this->notifier->send(
                $user->getEmail(),
                'Votre code de vérification',
                [
                    'otp' => $otp,
                    'otp_expiration' => $user->getOtpExpiration()->format('Y-m-d H:i:s')
                ],
                'emails/otp.html.twig'
            );

            return true;
        }

        $user->setLastLogin(new \DateTime('now', new \DateTimeZone('UTC')));
        $this->entityManager->flush();

        return $this->tokenAuthenticator->createAuthenticationToken($user);
    }

    /**
     * @param string $email
     * @param int $otp
     * @return array
     */
    public function verifyOtp(string $email, int $otp): array
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            throw new UserNotFoundException(ApiMessage::USER_NOT_FOUND->value);
        }

        if (intval($user->getOTP()) !== $otp) {
            throw new AuthenticationFailedException(ApiMessage::INVALID_OTP->value);
        }

        if ($user->getOtpExpiration() < $date) {
            throw new AuthenticationFailedException(ApiMessage::OTP_EXPIRED->value);
        }

        $user->setOTP(null);
        $user->setOtpExpiration(null);
        $user->setLastLogin(new \DateTime('now', new \DateTimeZone('UTC')));
        $this->entityManager->flush();

        return $this->tokenAuthenticator->createAuthenticationToken($user);
    }

    /**
     * @param string $email
     * @param string $password
     * @param string $username
     * @return array{userId: string, email: string, username: string}
     */
    public function register(string $email, string $password, string $username): array
    {
        // Validation des données
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RegistrationException(ApiMessage::INVALID_DATA->value);
        }

        if (empty($username)) {
            throw new RegistrationException(ApiMessage::INVALID_DATA->value);
        }

        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new RegistrationException(ApiMessage::INVALID_PASSWORD_FORMAT->value);
        }

        // Vérifier si l'email existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            throw new RegistrationException(ApiMessage::EMAIL_ALREADY_USED->value);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existingUser) {
            throw new RegistrationException(ApiMessage::INVALID_DATA->value);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setProvider([AuthProvider::CREDENTIALS->value]);

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return [
                'userId' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
            ];
        } catch (Exception $e) {
            throw new RegistrationException($e->getMessage());
        }
    }

    public function verifyJwt(string $token): array
    {
        return $this->tokenAuthenticator->verifyJwt($token);
    }
}
