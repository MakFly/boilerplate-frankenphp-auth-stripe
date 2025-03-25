# Documentation: Système d'Authentification SSO Google pour Symfony

## Introduction

Ce document présente l'implémentation d'un système d'authentification SSO (Single Sign-On) avec Google dans une application Symfony. Cette fonctionnalité permet aux utilisateurs de se connecter ou de créer un compte en utilisant leur compte Google, offrant ainsi une expérience d'inscription et de connexion simplifiée.

## Objectifs du système SSO

- **Simplifier l'inscription et la connexion** des utilisateurs en utilisant leurs comptes Google existants
- **Réduire les risques liés à la gestion des mots de passe** en déléguant l'authentification à Google
- **Améliorer l'expérience utilisateur** en offrant une option de connexion rapide
- **Créer ou lier automatiquement** des comptes utilisateur dans la base de données locale
- **Générer des jetons JWT** pour maintenir la session utilisateur côté client

## Architecture du Système

Le système SSO Google est organisé autour des composants suivants:

1. **Controller d'authentification**: Point d'entrée pour les requêtes d'authentification Google
2. **Service GoogleSsoService**: Logique de traitement des informations reçues de Google
3. **Entity User**: Modèle stockant les informations utilisateur, incluant le googleId
4. **Frontend NextJS**: Interface utilisateur utilisant better-auth pour gérer les connexions Google

### Diagramme de Flux

```
┌──────────────┐      ┌──────────────┐      ┌─────────────────┐      ┌────────────────┐
│   Frontend   │      │    API SSO   │      │ GoogleSsoService │      │      BDD       │
│   (NextJS)   │ ──── │  Controller  │ ──── │   Authentifier  │ ──── │ (User Entity)  │
│ better-auth  │      │    (POST)    │      │ & Create/Update │      │                │
└──────────────┘      └──────────────┘      └─────────────────┘      └────────────────┘
                                                    │
                                                    ▼
                                         ┌────────────────────┐
                                         │     JWT Token      │
                                         │    Generation      │
                                         └────────────────────┘
```

## Implémentation Backend (Symfony)

### 1. Configuration et Prérequis

La configuration requise dans le projet Symfony:

```yaml
# .env
GOOGLE_CLIENT_ID=votre-google-client-id
GOOGLE_CLIENT_SECRET=votre-google-client-secret
```

### 2. Entité User

L'entité User est configurée pour stocker les informations relatives au SSO:

```php
// src/Entity/User.php
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Champs existants...
    
    #[ORM\Column(type: 'boolean')]
    private bool $isSsoLinked = false;

    #[ORM\Column(type: 'json')]
    private array $linkedAccounts = [];

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $googleId = null;
    
    // Getters et setters...
    
    public function getIsSsoLinked(): bool
    {
        return $this->isSsoLinked;
    }

    public function setIsSsoLinked(bool $isSsoLinked): static
    {
        $this->isSsoLinked = $isSsoLinked;
        return $this;
    }

    public function getLinkedAccounts(): array
    {
        return $this->linkedAccounts;
    }

    public function addLinkedAccount(string $provider, string $externalId): static
    {
        // Implémentation...
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }
}
```

### 3. Controller d'authentification SSO

```php
// src/Controller/Api/AuthController.php
#[Route('/sso', name: 'sso', methods: ['POST'])]
public function sso(Request $request): JsonResponse
{
    // Récupération et validation du token
    $data = json_decode($request->getContent(), true);
    if (!$data || !isset($data['account']['id_token'])) {
        return new JsonResponse(['error' => 'Données invalides'], JsonResponse::HTTP_BAD_REQUEST);
    }

    $idToken = $data['account']['id_token'];
    // Vérification du token Google
    $payload = $this->googleClient->verifyIdToken($idToken);
    if (!$payload) {
        return new JsonResponse(['error' => 'Token invalide'], JsonResponse::HTTP_UNAUTHORIZED);
    }

    // Récupération des infos Google
    $googleId = $payload['sub'];
    $email = $payload['email'] ?? null;
    $name = $payload['name'] ?? '';

    // Traitement de l'utilisateur en base
    $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => $googleId]);
    if (!$user) {
        // Création d'un nouvel utilisateur
        $user = new User();
        $user->setGoogleId($googleId);
        $user->setEmail($email);
        $user->setUsername($name);
        $user->setIsSsoLinked(true);
        $user->addProvider(AuthProvider::GOOGLE->value);
    } else {
        // Mise à jour de l'utilisateur existant
        $user->setEmail($email);
        $user->setUsername($name);
    }

    $this->em->persist($user);
    $this->em->flush();

    // Génération de token JWT et réponse
    $token = $this->tokenAuthenticator->createAuthenticationToken($user);
    
    return new JsonResponse([
        'status' => 'success',
        'data' => [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'token' => $token['token'],
            'token_expires_in' => $token['token_expires_in']
        ]
    ]);
}
```

### 4. Service GoogleSsoService

Pour une meilleure séparation des préoccupations, le controller devrait utiliser un service dédié:

```php
// src/Service/Auth/GoogleSsoService.php
namespace App\Service\Auth;

use App\Entity\User;
use App\Enum\AuthProvider;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class GoogleSsoService
{
    // Logique de gestion de l'authentification Google
    // Voir le code existant pour référence
    
    public function handleGoogleAuthentication(array $googleData): User
    {
        $googleId = $googleData['sub'];
        
        // Recherche ou création d'utilisateur
        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);
        if (!$user) {
            $user = $this->userRepository->findOneBy(['email' => $googleData['email']]);
            
            if ($user) {
                // Lier un compte Google à un utilisateur existant
                $user->setGoogleId($googleId);
                $user->addLinkedAccount(AuthProvider::GOOGLE->value, $googleId);
                $user->addProvider(AuthProvider::GOOGLE->value);
                $user->setIsSsoLinked(true);
            } else {
                // Créer un nouvel utilisateur
                $user = new User();
                $user->setEmail($googleData['email']);
                $user->setUsername($googleData['name'] ?? $googleData['email']);
                $user->setGoogleId($googleId);
                $user->setProvider([AuthProvider::GOOGLE->value]);
                $user->setIsSsoLinked(true);
            }
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        return $user;
    }
}
```

### 5. Configuration des Routes

```yaml
# config/routes.yaml ou via annotations
api_auth_sso:
    path: /api/auth/sso
    controller: App\Controller\Api\AuthController::sso
    methods: ['POST']
```

### 6. Configuration du Firewall pour le SSO

```yaml
# config/packages/security.yaml
security:
    # ...
    access_control:
        - { path: ^/api/auth/sso, roles: PUBLIC_ACCESS }
```

## Implémentation Frontend (NextJS avec better-auth)

### 1. Configuration de better-auth

```tsx
// auth-config.ts
import { GoogleProvider } from "better-auth/providers/google";
import { AuthConfig } from "better-auth";

export const authConfig: AuthConfig = {
  providers: [
    GoogleProvider({
      clientId: process.env.NEXT_PUBLIC_GOOGLE_CLIENT_ID!,
      prompt: "select_account",
    }),
  ],
  callbacks: {
    async signIn({ account, user }) {
      if (account && account.provider === "google") {
        try {
          // Envoyer le token à notre API backend
          const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/api/auth/sso`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              account,
              user,
            }),
          });

          const data = await response.json();
          
          if (response.ok) {
            // Stocker le JWT dans le localStorage ou Cookie
            localStorage.setItem("jwtToken", data.data.token);
            return true;
          }
          
          return false;
        } catch (error) {
          console.error("SSO authentication error:", error);
          return false;
        }
      }
      return false;
    },
  },
};
```

### 2. Composant Login avec Bouton Google

```tsx
// components/LoginForm.tsx
import { useAuth } from "better-auth/react";
import { Button } from "./ui/button";

export function LoginForm() {
  const { signIn } = useAuth();

  const handleGoogleLogin = async () => {
    await signIn("google");
  };

  return (
    <div className="flex flex-col gap-4">
      <h2>Connexion</h2>
      
      {/* Autres méthodes de connexion... */}
      
      <div className="divider">OU</div>
      
      <Button 
        variant="outline" 
        onClick={handleGoogleLogin}
        className="flex items-center gap-2"
      >
        <GoogleLogo />
        Se connecter avec Google
      </Button>
    </div>
  );
}
```

### 3. Gestion de la Session Utilisateur

```tsx
// hooks/useUser.ts
import { useEffect, useState } from "react";
import { jwtDecode } from "jwt-decode";

export function useUser() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem("jwtToken");
    if (token) {
      try {
        const decoded = jwtDecode(token);
        setUser(decoded);
      } catch (error) {
        console.error("Invalid token:", error);
        localStorage.removeItem("jwtToken");
      }
    }
    setLoading(false);
  }, []);

  const logout = () => {
    localStorage.removeItem("jwtToken");
    setUser(null);
  };

  return { user, loading, logout };
}
```

## Workflow Complet

### Processus d'authentification SSO

1. **Action de l'utilisateur**: L'utilisateur clique sur le bouton "Se connecter avec Google"
2. **Frontend**: better-auth affiche la boîte de dialogue Google permettant de choisir un compte
3. **Google OAuth**: Google authentifie l'utilisateur et renvoie un token ID
4. **Frontend**: better-auth intercepte le callback et envoie le token à l'API backend
5. **Backend**: 
   - Vérifie le token avec l'API Google
   - Recherche l'utilisateur par googleId ou email
   - Crée ou met à jour l'utilisateur en base de données
   - Génère un token JWT
6. **Frontend**: Stocke le JWT et met à jour l'état de l'application pour refléter la connexion utilisateur

### Diagramme de Séquence

```
┌────────┐          ┌──────────┐          ┌───────┐          ┌───────────┐          ┌─────┐
│ Client │          │ Frontend │          │ Google│          │   API     │          │ BDD │
└───┬────┘          └────┬─────┘          └───┬───┘          └─────┬─────┘          └──┬──┘
    │    Click login     │                    │                     │                   │
    │ ─────────────────> │                    │                     │                   │
    │                    │    OAuth request   │                     │                   │
    │                    │ ─────────────────> │                     │                   │
    │                    │                    │                     │                   │
    │                    │   Account choice   │                     │                   │
    │ <─────────────────────────────────────> │                     │                   │
    │                    │                    │                     │                   │
    │                    │   Token returned   │                     │                   │
    │                    │ <───────────────── │                     │                   │
    │                    │                    │                     │                   │
    │                    │   POST /api/auth/sso (token)             │                   │
    │                    │ ──────────────────────────────────────>  │                   │
    │                    │                    │                     │  Find/Create User │
    │                    │                    │                     │ ────────────────> │
    │                    │                    │                     │                   │
    │                    │                    │                     │  User returned    │
    │                    │                    │                     │ <──────────────── │
    │                    │                    │                     │                   │
    │                    │   JWT response     │                     │                   │
    │                    │ <────────────────────────────────────────│                   │
    │                    │                    │                     │                   │
    │   Auth success     │                    │                     │                   │
    │ <───────────────── │                    │                     │                   │
    │                    │                    │                     │                   │
```

## Bonnes Pratiques et Améliorations

### Sécurité

- **Vérification du token**: Toujours vérifier le token ID avec l'API Google
- **Stockage sécurisé**: Utiliser httpOnly cookies pour les jetons JWT plutôt que localStorage
- **CSRF Protection**: Implémenter des protections contre les attaques CSRF
- **Rate Limiting**: Limiter le nombre de requêtes d'authentification pour prévenir les abus

### Expérience Utilisateur

- **Gestion des erreurs**: Afficher des messages d'erreur clairs en cas d'échec d'authentification
- **Liaison de comptes**: Permettre aux utilisateurs de lier/délier leurs comptes Google depuis leur profil
- **Fallback**: Proposer une méthode d'authentification alternative en cas d'échec du SSO

### Architecture

- **Clean Code**: Séparer la logique d'authentification dans des services dédiés
- **Tests**: Écrire des tests pour les flux d'authentification SSO
- **Logs**: Implémenter des logs détaillés pour faciliter le débogage

## Conclusion

L'implémentation d'un système SSO Google dans une application Symfony avec un frontend NextJS offre une expérience utilisateur simplifiée tout en maintenant un niveau de sécurité élevé. Cette architecture permet une gestion flexible des identités utilisateur, avec la possibilité de créer de nouveaux comptes ou de lier des comptes existants selon les besoins.

La combinaison de better-auth côté frontend et du système d'authentification JWT côté backend crée un flux cohérent et sécurisé pour l'identification des utilisateurs à travers l'application.