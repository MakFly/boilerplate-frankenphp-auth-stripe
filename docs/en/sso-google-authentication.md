# Documentation: Google SSO Authentication System for Symfony

## Introduction

This document presents the implementation of a Google SSO (Single Sign-On) authentication system in a Symfony application. This feature allows users to sign in or create an account using their Google account, providing a simplified registration and login experience.

## SSO System Objectives

- **Simplify registration and login** for users using their existing Google accounts
- **Reduce password management risks** by delegating authentication to Google
- **Improve user experience** by providing a quick login option
- **Automatically create or link** user accounts in the local database
- **Generate JWT tokens** to maintain client-side user sessions

## System Architecture

The Google SSO system is organized around the following components:

1. **Authentication Controller**: Entry point for Google authentication requests
2. **GoogleSsoService**: Processing logic for information received from Google
3. **User Entity**: Model storing user information, including googleId
4. **Frontend NextJS**: User interface using better-auth to handle Google connections

### Flow Diagram

```
┌──────────────┐      ┌──────────────┐      ┌─────────────────┐      ┌────────────────┐
│   Frontend   │      │    SSO API   │      │ GoogleSsoService │      │    Database    │
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

## Backend Implementation (Symfony)

### 1. Configuration and Prerequisites

Required configuration in the Symfony project:

```yaml
# .env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
```

### 2. User Entity

The User entity is configured to store SSO-related information:

```php
// src/Entity/User.php
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Existing fields...
    
    #[ORM\Column(type: 'boolean')]
    private bool $isSsoLinked = false;

    #[ORM\Column(type: 'json')]
    private array $linkedAccounts = [];

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $googleId = null;
    
    // Getters and setters...
    
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
        // Implementation...
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

### 3. SSO Authentication Controller

```php
// src/Controller/Api/AuthController.php
#[Route('/sso', name: 'sso', methods: ['POST'])]
public function sso(Request $request): JsonResponse
{
    // Get and validate token
    $data = json_decode($request->getContent(), true);
    if (!$data || !isset($data['account']['id_token'])) {
        return new JsonResponse(['error' => 'Invalid data'], JsonResponse::HTTP_BAD_REQUEST);
    }

    $idToken = $data['account']['id_token'];
    // Verify Google token
    $payload = $this->googleClient->verifyIdToken($idToken);
    if (!$payload) {
        return new JsonResponse(['error' => 'Invalid token'], JsonResponse::HTTP_UNAUTHORIZED);
    }

    // Get Google info
    $googleId = $payload['sub'];
    $email = $payload['email'] ?? null;
    $name = $payload['name'] ?? '';

    // Process user in database
    $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => $googleId]);
    if (!$user) {
        // Create new user
        $user = new User();
        $user->setGoogleId($googleId);
        $user->setEmail($email);
        $user->setUsername($name);
        $user->setIsSsoLinked(true);
        $user->addProvider(AuthProvider::GOOGLE->value);
    } else {
        // Update existing user
        $user->setEmail($email);
        $user->setUsername($name);
    }

    $this->em->persist($user);
    $this->em->flush();

    // Generate JWT token and response
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

### 4. GoogleSsoService

For better separation of concerns, the controller should use a dedicated service:

```php
// src/Service/Auth/GoogleSsoService.php
namespace App\Service\Auth;

use App\Entity\User;
use App\Enum\AuthProvider;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class GoogleSsoService
{
    // Google authentication management logic
    // See existing code for reference
    
    public function handleGoogleAuthentication(array $googleData): User
    {
        $googleId = $googleData['sub'];
        
        // Find or create user
        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);
        if (!$user) {
            $user = $this->userRepository->findOneBy(['email' => $googleData['email']]);
            
            if ($user) {
                // Link Google account to existing user
                $user->setGoogleId($googleId);
                $user->addLinkedAccount(AuthProvider::GOOGLE->value, $googleId);
                $user->addProvider(AuthProvider::GOOGLE->value);
                $user->setIsSsoLinked(true);
            } else {
                // Create new user
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

### 5. Route Configuration

```yaml
# config/routes.yaml or via annotations
api_auth_sso:
    path: /api/auth/sso
    controller: App\Controller\Api\AuthController::sso
    methods: ['POST']
```

### 6. Firewall Configuration for SSO

```yaml
# config/packages/security.yaml
security:
    # ...
    access_control:
        - { path: ^/api/auth/sso, roles: PUBLIC_ACCESS }
```

## Frontend Implementation (NextJS with better-auth)

### 1. better-auth Configuration

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
          // Send token to our backend API
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
            // Store JWT in localStorage or Cookie
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

### 2. Login Component with Google Button

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
      <h2>Login</h2>
      
      {/* Other login methods... */}
      
      <div className="divider">OR</div>
      
      <Button 
        variant="outline" 
        onClick={handleGoogleLogin}
        className="flex items-center gap-2"
      >
        <GoogleLogo />
        Sign in with Google
      </Button>
    </div>
  );
}
```

### 3. User Session Management

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

## Complete Workflow

### SSO Authentication Process

1. **User Action**: User clicks "Sign in with Google" button
2. **Frontend**: better-auth displays Google dialog to choose an account
3. **Google OAuth**: Google authenticates user and returns an ID token
4. **Frontend**: better-auth intercepts callback and sends token to backend API
5. **Backend**: 
   - Verifies token with Google API
   - Searches for user by googleId or email
   - Creates or updates user in database
   - Generates JWT token
6. **Frontend**: Stores JWT and updates application state to reflect user login

### Sequence Diagram

```
┌────────┐          ┌──────────┐          ┌───────┐          ┌───────────┐          ┌─────┐
│ Client │          │ Frontend │          │Google │          │    API    │          │ DB  │
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

## Best Practices and Improvements

### Security

- **Token Verification**: Always verify ID token with Google API
- **Secure Storage**: Use httpOnly cookies for JWT tokens rather than localStorage
- **CSRF Protection**: Implement protections against CSRF attacks
- **Rate Limiting**: Limit authentication requests to prevent abuse

### User Experience

- **Error Handling**: Display clear error messages in case of authentication failure
- **Account Linking**: Allow users to link/unlink their Google accounts from their profile
- **Fallback**: Provide alternative authentication method if SSO fails

### Architecture

- **Clean Code**: Separate authentication logic into dedicated services
- **Tests**: Write tests for SSO authentication flows
- **Logs**: Implement detailed logging for debugging

## Conclusion

The combination of better-auth on the frontend and JWT authentication system on the backend creates a coherent and secure flow for user identification across the application.