<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\DTO\Auth\LoginRequest;
use App\Entity\User;
use App\Enum\ApiMessage;
use App\Enum\AuthProvider;
use App\Exception\Auth\AuthenticationFailedException;
use App\Exception\Auth\RegistrationException;
use App\Interface\Auth\AuthInterface;
use App\Interface\Auth\AuthOptionsInterface;
use App\Interface\NotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class AuthService implements AuthInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly NotifierInterface $notifier,
        private readonly TokenAuthenticator $tokenAuthenticator,
        private readonly AuthOptionsInterface $authOptions,
    ) {
    }

    /**
     * @param LoginRequest $loginRequest
     * @return array|bool
     */
    public function authCustom(LoginRequest $loginRequest): array|bool
    {
        $user = $this->findUserByEmail($loginRequest->getEmail());

        if ($this->authOptions->isOTPEnabled()) {
            return $this->handleOTPAuthentication($user);
        }

        return $this->handleDirectAuthentication($user);
    }

    private function findUserByEmail(string $email): User
    {
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            throw new UserNotFoundException(ApiMessage::USER_NOT_FOUND->value);
        }

        return $user;
    }

    private function handleOTPAuthentication(User $user): bool
    {
        // TODO: Check if OTP already sent to avoid spam
        $otp = $this->generateOTP();
        
        $user->setOTP(strval($otp));
        $user->setOtpExpiration(new \DateTime('+5 minutes', new \DateTimeZone('UTC')));
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->sendOTPNotification($user, $otp);

        return true;
    }

    private function handleDirectAuthentication(User $user): array
    {
        $user->setLastLogin(new \DateTime('now', new \DateTimeZone('UTC')));
        $this->entityManager->flush();

        return $this->tokenAuthenticator->createAuthenticationToken($user);
    }

    private function generateOTP(): int
    {
        return rand(100000, 999999);
    }

    private function sendOTPNotification(User $user, int $otp): void
    {
        $this->notifier->send(
            $user->getEmail(),
            'Votre code de vérification',
            [
                'otp' => $otp,
                'otp_expiration' => $user->getOtpExpiration()->format('Y-m-d H:i:s')
            ],
            ['template' => 'emails/otp.html.twig']
        );
    }

    /**
     * @param string $email
     * @param int $otp
     * @return array
     */
    public function verifyOtp(string $email, int $otp): array
    {
        $user = $this->findUserByEmail($email);
        
        $this->validateOTP($user, $otp);
        $this->clearOTP($user);
        $this->updateLastLogin($user);

        return $this->tokenAuthenticator->createAuthenticationToken($user);
    }

    private function validateOTP(User $user, int $otp): void
    {
        if (intval($user->getOTP()) !== $otp) {
            throw new AuthenticationFailedException(ApiMessage::INVALID_OTP->value);
        }

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        if ($user->getOtpExpiration() < $now) {
            throw new AuthenticationFailedException(ApiMessage::OTP_EXPIRED->value);
        }
    }

    private function clearOTP(User $user): void
    {
        $user->setOTP(null);
        $user->setOtpExpiration(null);
    }

    private function updateLastLogin(User $user): void
    {
        $user->setLastLogin(new \DateTime('now', new \DateTimeZone('UTC')));
        $this->entityManager->flush();
    }

    /**
     * @param string $email
     * @param string $password
     * @param string $username
     * @return array{userId: string, email: string, username: string}
     */
    public function register(string $email, string $password, string $username): array
    {
        $this->validateRegistrationData($email, $password, $username);
        $this->checkUserUniqueness($email, $username);
        
        $user = $this->createUser($email, $password, $username);
        
        return $this->persistUser($user);
    }

    private function validateRegistrationData(string $email, string $password, string $username): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RegistrationException(ApiMessage::INVALID_DATA->value);
        }

        if (empty($username)) {
            throw new RegistrationException(ApiMessage::INVALID_DATA->value);
        }

        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new RegistrationException(ApiMessage::INVALID_PASSWORD_FORMAT->value);
        }
    }

    private function checkUserUniqueness(string $email, string $username): void
    {
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            throw new RegistrationException(ApiMessage::EMAIL_ALREADY_USED->value);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existingUser) {
            throw new RegistrationException(ApiMessage::INVALID_DATA->value);
        }
    }

    private function createUser(string $email, string $password, string $username): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setProvider([AuthProvider::CREDENTIALS->value]);

        return $user;
    }

    private function persistUser(User $user): array
    {
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
