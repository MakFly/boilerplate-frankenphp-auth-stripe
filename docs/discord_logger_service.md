# Utilisation des Loggers Discord dans un Service

Ce document présente des exemples avancés d'utilisation des loggers Discord dans vos services Symfony.

## Bonnes pratiques

- Utilisez l'autowiring avec l'attribut `#[Autowire]` (PHP 8.0+)
- Nommez vos loggers selon leur fonction (errorLogger, systemLogger, etc.)
- Utilisez les niveaux de log appropriés
- Incluez toujours un contexte structuré avec vos logs
- Ne loggez jamais d'informations sensibles

## Service Exemple avec Autowiring

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        
        #[Autowire('@monolog.logger.discord_error')]
        private readonly LoggerInterface $errorLogger,
        
        #[Autowire('@monolog.logger.discord_system')]
        private readonly LoggerInterface $systemLogger,
        
        #[Autowire('@monolog.logger.discord_info')]
        private readonly LoggerInterface $infoLogger
    ) {
    }
    
    /**
     * Crée un nouvel utilisateur
     */
    public function createUser(string $email, string $name): User
    {
        $this->systemLogger->info('Démarrage création utilisateur', [
            'email' => $email,
            'name' => $name
        ]);
        
        try {
            // Vérifier si l'utilisateur existe déjà
            if ($this->userRepository->findOneByEmail($email)) {
                $this->systemLogger->notice('Tentative de création d\'un utilisateur existant', [
                    'email' => $email
                ]);
                
                throw new \RuntimeException('Un utilisateur avec cet email existe déjà');
            }
            
            // Créer l'utilisateur
            $user = new User();
            $user->setEmail($email);
            $user->setName($name);
            
            $this->userRepository->save($user, true);
            
            $this->infoLogger->notice('Utilisateur créé avec succès', [
                'user_id' => $user->getId(),
                'email' => $email
            ]);
            
            return $user;
        } catch (\Exception $e) {
            $this->errorLogger->error('Échec de création d\'utilisateur', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'email' => $email,
                'name' => $name
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Met à jour un utilisateur existant
     */
    public function updateUser(int $userId, array $data): User
    {
        try {
            $user = $this->userRepository->find($userId);
            
            if (!$user) {
                $this->systemLogger->warning('Tentative de mise à jour d\'un utilisateur inexistant', [
                    'user_id' => $userId
                ]);
                
                throw new \RuntimeException('Utilisateur non trouvé');
            }
            
            $this->infoLogger->info('Mise à jour utilisateur', [
                'user_id' => $userId,
                'fields' => array_keys($data)
            ]);
            
            // Mettre à jour l'utilisateur
            if (isset($data['name'])) {
                $user->setName($data['name']);
            }
            
            if (isset($data['email'])) {
                $user->setEmail($data['email']);
            }
            
            $this->userRepository->save($user, true);
            
            $this->systemLogger->notice('Utilisateur mis à jour avec succès', [
                'user_id' => $userId
            ]);
            
            return $user;
        } catch (\Exception $e) {
            $this->errorLogger->error('Échec de mise à jour d\'utilisateur', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
```

## Logging dans un service de paiement

Voici un exemple plus concret d'utilisation des loggers Discord dans un service de paiement:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        
        #[Autowire('@monolog.logger.discord_error')]
        private readonly LoggerInterface $errorLogger,
        
        #[Autowire('@monolog.logger.discord_system')]
        private readonly LoggerInterface $systemLogger
    ) {
    }
    
    public function processPayment(int $userId, float $amount, string $method): Payment
    {
        $correlationId = bin2hex(random_bytes(8)); // ID unique pour tracer la transaction
        
        // Démarrage du processus
        $this->systemLogger->info('Démarrage traitement paiement', [
            'correlation_id' => $correlationId,
            'user_id' => $userId,
            'amount' => $amount,
            'method' => $method
        ]);
        
        try {
            // Votre logique de traitement de paiement
            // ...
            
            $payment = new Payment();
            $payment->setUserId($userId);
            $payment->setAmount($amount);
            $payment->setMethod($method);
            $payment->setStatus('completed');
            $payment->setReference('PAY-' . $correlationId);
            
            $this->paymentRepository->save($payment, true);
            
            // Log de succès
            $this->systemLogger->notice('Paiement traité avec succès', [
                'correlation_id' => $correlationId,
                'payment_id' => $payment->getId(),
                'user_id' => $userId,
                'amount' => $amount,
                'reference' => $payment->getReference()
            ]);
            
            return $payment;
        } catch (\Exception $e) {
            // Log détaillé en cas d'erreur
            $this->errorLogger->error('Échec du traitement de paiement', [
                'correlation_id' => $correlationId,
                'exception' => get_class($e),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'amount' => $amount,
                'method' => $method,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException(
                'Le paiement a échoué: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    public function refundPayment(int $paymentId): void
    {
        $correlationId = bin2hex(random_bytes(8));
        
        $this->systemLogger->info('Demande de remboursement', [
            'correlation_id' => $correlationId,
            'payment_id' => $paymentId
        ]);
        
        try {
            $payment = $this->paymentRepository->find($paymentId);
            
            if (!$payment) {
                $this->errorLogger->warning('Tentative de remboursement d\'un paiement inexistant', [
                    'correlation_id' => $correlationId,
                    'payment_id' => $paymentId
                ]);
                
                throw new \RuntimeException('Paiement non trouvé');
            }
            
            // Votre logique de remboursement
            // ...
            
            $payment->setStatus('refunded');
            $this->paymentRepository->save($payment, true);
            
            $this->systemLogger->notice('Remboursement effectué avec succès', [
                'correlation_id' => $correlationId,
                'payment_id' => $paymentId,
                'amount' => $payment->getAmount(),
                'user_id' => $payment->getUserId()
            ]);
        } catch (\Exception $e) {
            $this->errorLogger->error('Échec du remboursement', [
                'correlation_id' => $correlationId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'payment_id' => $paymentId
            ]);
            
            throw $e;
        }
    }
}
```

## Recommandations pour le logging

1. **Utilisez des Correlation IDs**: Ils permettent de suivre les demandes à travers différents services
2. **Structures hiérarchiques**: Utilisez une structure cohérente pour vos données de contexte 
3. **Décodez les exceptions**: Loggez toujours la classe d'exception, le message et la trace
4. **Standardisez les noms des champs**: Utilisez toujours les mêmes noms pour les mêmes types de données
5. **Utilisez les bons niveaux**: 
   - `debug`: Informations détaillées de débogage
   - `info`: Actions normales du système
   - `notice`: Événements significatifs mais normaux
   - `warning`: Avertissements non critiques
   - `error`: Erreurs applicatives
   - `critical`: Défaillances systèmes
   - `alert`: Actions immédiates requises
   - `emergency`: Panne complète du système 