<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Enum\AuthProvider;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google_Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final readonly class GoogleSsoService
{
    private Google_Client $googleClient;

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private TokenAuthenticator $tokenAuthenticator,
        #[Autowire(env: 'GOOGLE_CLIENT_ID')]
        private string $googleClientId,
    ) {
        $this->googleClient = new Google_Client(['client_id' => $this->googleClientId]);
    }

    public function authenticateWithGoogle(string $idToken): array
    {
        $payload = $this->verifyIdToken($idToken);
        if (!$payload) {
            throw new \RuntimeException('Token invalide');
        }

        $user = $this->handleNormalAuthentication($payload['sub'], $payload);
        
        return $this->tokenAuthenticator->createAuthenticationToken($user);
    }

    public function verifyIdToken(string $idToken): ?array
    {
        return $this->googleClient->verifyIdToken($idToken);
    }

    /**
     * @param array{sub: string, email: string} $googleData Les données de l'utilisateur Google
     */
    public function handleGoogleAuthentication(array $googleData, ?string $currentUserEmail = null): User
    {
        $googleId = $googleData['sub'];
        $email = $googleData['email'];
        
        // Si un utilisateur est déjà connecté, on gère la liaison de compte
        if ($currentUserEmail) {
            return $this->handleAccountLinking($currentUserEmail, $googleId, $email);
        }
        
        // Sinon, on gère l'authentification normale
        return $this->handleNormalAuthentication($googleId, $googleData);
    }

    private function handleAccountLinking(string $currentUserEmail, string $googleId, string $googleEmail): User
    {
        // Vérifier si l'utilisateur existe
        $user = $this->userRepository->findOneBy(['email' => $currentUserEmail]);
        if (!$user) {
            throw new UserNotFoundException('Utilisateur non trouvé');
        }

        // Vérifier si le compte Google n'est pas déjà lié à un autre compte
        $existingGoogleUser = $this->userRepository->findOneBy(['googleId' => $googleId]);
        if ($existingGoogleUser && $existingGoogleUser->getId() !== $user->getId()) {
            throw new \RuntimeException('Ce compte Google est déjà lié à un autre compte');
        }

        // Lier le compte Google
        $user->setGoogleId($googleId);
        $user->addLinkedAccount(AuthProvider::GOOGLE->value, $googleId);
        $user->addProvider(AuthProvider::GOOGLE->value);
        $user->setIsSsoLinked(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param array{sub: string, email: string, given_name?: string, family_name?: string} $googleData Les données de l'utilisateur Google
     */
    private function handleNormalAuthentication(string $googleId, array $googleData): User
    {
        // Chercher d'abord par googleId
        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);
        
        // Si non trouvé, chercher par email
        if (!$user) {
            $user = $this->userRepository->findOneBy(['email' => $googleData['email']]);
            
            if ($user) {
                // L'utilisateur existe mais n'est pas encore lié à Google
                $user->setGoogleId($googleId);
                $user->addLinkedAccount(AuthProvider::GOOGLE->value, $googleId);
                $user->addProvider(AuthProvider::GOOGLE->value);
                $user->setIsSsoLinked(true);
            } else {
                // Créer un nouvel utilisateur
                $user = new User();
                $user->setEmail($googleData['email']);
                $user->setUsername($googleData['email']); // ou utiliser given_name + family_name
                $user->setGoogleId($googleId);
                $user->setProvider([AuthProvider::GOOGLE->value]);
                $user->addLinkedAccount(AuthProvider::GOOGLE->value, $googleId);
                $user->setIsSsoLinked(true);
            }
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        return $user;
    }

    public function unlinkGoogleAccount(User $user): void
    {
        if (!$user->hasProvider(AuthProvider::GOOGLE->value)) {
            throw new \RuntimeException('Ce compte n\'est pas lié à Google');
        }

        // S'assurer qu'il existe au moins un autre moyen de connexion
        if (count($user->getProvider()) <= 1) {
            throw new \RuntimeException('Impossible de délier le compte Google car c\'est le seul moyen de connexion');
        }

        $user->removeProvider(AuthProvider::GOOGLE->value);
        $user->removeLinkedAccount(AuthProvider::GOOGLE->value);
        $user->setGoogleId(null);
        $user->setIsSsoLinked(false);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}