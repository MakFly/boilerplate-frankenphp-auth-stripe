<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\ApiMessage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class CustomJsonLoginAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param Request $request
     * @return boolean|null
     */
    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/api/auth/login' && $request->isMethod('POST');
    }

    /**
     * @param Request $request
     * @return Passport
     */
    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true);
        
        /** @var User|null $user */
        $user = $this->userRepository->findOneBy(['email' => $data['email']]);
        
        // Vérifier si le compte est verrouillé
        if ($user && $user->getLockedUntil() !== null && $user->getLockedUntil() > new \DateTime('now', new \DateTimeZone('UTC'))) {
            throw new AuthenticationException(ApiMessage::ACCOUNT_LOCKED->value);
        }
        
        // Vérifier si l'authentification par mot de passe est valide
        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            // Vérifier si le verrouillage de compte est activé pour cette requête
            $isAccountLockingEnabled = $request->attributes->get('account_locking_enabled', true);
            
            if ($user && $isAccountLockingEnabled) {
                // Incrémenter le compteur de tentatives échouées
                $user->setFailedLoginAttempts($user->getFailedLoginAttempts() + 1);
                
                // Verrouiller le compte après 3 tentatives échouées
                if ($user->getFailedLoginAttempts() >= 3) {
                    $user->setLockedUntil(new \DateTime('+1 hour', new \DateTimeZone('UTC')));
                }
                
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }
            
            throw new AuthenticationException(ApiMessage::INVALID_CREDENTIALS->value);
        }
        
        // Réinitialiser le compteur de tentatives échouées en cas de succès
        $isAccountLockingEnabled = $request->attributes->get('account_locking_enabled', true);
        if ($isAccountLockingEnabled) {
            $user->setFailedLoginAttempts(0);
            $user->setLockedUntil(null);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        return new Passport(new UserBadge($data['email']), new PasswordCredentials($data['password']));
    }

    /**
     * @param Request $request
     * @param TokenInterface $token
     * @param string $firewallName
     * @return Response|null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Return null for the default behaviour
        return null;
    }

    /**
     * @param Request $request
     * @param AuthenticationException $exception
     * @return JsonResponse|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?JsonResponse
    {
        return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }
}