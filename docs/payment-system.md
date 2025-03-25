# Documentation: Système de Paiement Stripe pour Symfony

## Introduction

Ce document présente l'implémentation d'un système de paiement complet utilisant Stripe avec Symfony, basé sur les patterns Factory et Decorator. Cette architecture permet de gérer facilement différents types de paiements (ponctuels via Payment Intents et récurrents via Subscriptions) tout en respectant les principes SOLID et en assurant une extensibilité maximale.

## Architecture du Système

Le système est structuré autour de plusieurs composants clés:

1. **PaymentServiceInterface**: Définit le contrat pour tous les services de paiement
2. **Implémentations spécifiques**: Services concrets pour chaque type de paiement
3. **PaymentLoggerDecorator**: Enrichit les services avec la persistance des données
4. **PaymentServiceFactory**: Crée dynamiquement le service approprié selon le type
5. **Webhooks**: Gestion des événements Stripe pour mettre à jour les statuts
6. **Système de factures**: Génération automatique de factures pour les paiements ponctuels et abonnements
7. **Mécanisme de désactivation**: Possibilité de désactiver le système de paiement globalement ou spécifiquement

### Diagramme de Classes

```
PaymentServiceInterface
       ↑
       |
       ├─── PaymentIntentService
       └─── SubscriptionService
       
                   ┌───────────────┐
                   │               │
PaymentLoggerDecorator ◄──── PaymentServiceFactory
                   │               │
                   └───────────────┘
           
Entities: Payment, Subscription, Invoice
```

## Implémentation

### 1. Interface PaymentServiceInterface

```php
<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

interface PaymentServiceInterface
{
    /**
     * Crée une session de paiement avec Stripe
     * 
     * @param User $user Utilisateur qui effectue le paiement
     * @param int $amount Montant en centimes
     * @param string $currency Devise (ex: 'eur')
     * @param array $metadata Métadonnées additionnelles
     * @return array Informations de session avec les clés nécessaires pour le front-end
     */
    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array;
    
    /**
     * Traite un webhook Stripe selon le type d'événement
     * 
     * @param string $eventType Type d'événement Stripe
     * @param array $eventData Données de l'événement
     * @return bool Succès ou échec du traitement
     */
    public function handleWebhook(string $eventType, array $eventData): bool;
}
```

### 2. Entités pour la persistance des données

#### Entity Payment

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $stripeId;

    #[ORM\Column(length: 50)]
    private string $status;

    #[ORM\Column]
    private int $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private string $paymentType;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    // Getters, setters et constructeur omis pour la brièveté
}
```

#### Entity Subscription

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $stripeId;

    #[ORM\Column(length: 255)]
    private string $stripePlanId;

    #[ORM\Column(length: 50)]
    private string $status;

    #[ORM\Column]
    private int $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 50)]
    private string $interval;

    #[ORM\Column]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    private bool $autoRenew = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    // Getters, setters et constructeur omis pour la brièveté
}
```

### 3. Implémentations des Services de Paiement

#### PaymentIntentService

```php
<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\User;
use App\Interface\PaymentServiceInterface;
use App\Repository\PaymentRepository;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

final readonly class PaymentIntentService implements PaymentServiceInterface
{
    private const PAYMENT_TYPE = 'payment_intent';
    private const STATUS_PENDING = 'pending';
    private const STATUS_SUCCEEDED = 'succeeded';
    private const STATUS_FAILED = 'failed';
    
    public function __construct(
        private string $stripeSecretKey,
        private PaymentRepository $paymentRepository,
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $amount,
                'currency' => $currency,
                'payment_method_types' => ['card'],
                'metadata' => array_merge($metadata, [
                    'user_id' => $user->getId()->toRfc4122(),
                ]),
            ]);
            
            return [
                'clientSecret' => $paymentIntent->client_secret,
                'id' => $paymentIntent->id,
                'amount' => $amount,
                'currency' => $currency,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Erreur lors de la création du PaymentIntent: ' . $e->getMessage());
        }
    }
    
    public function handleWebhook(string $eventType, array $eventData): bool
    {
        $paymentIntentId = $eventData['id'] ?? null;
        
        if (!$paymentIntentId) {
            return false;
        }
        
        $payment = $this->paymentRepository->findOneByStripeId($paymentIntentId);
        
        if (!$payment) {
            return false;
        }
        
        switch ($eventType) {
            case 'payment_intent.succeeded':
                $payment->setStatus(self::STATUS_SUCCEEDED);
                $this->paymentRepository->save($payment);
                return true;
                
            case 'payment_intent.payment_failed':
                $payment->setStatus(self::STATUS_FAILED);
                $this->paymentRepository->save($payment);
                return true;
                
            default:
                return false;
        }
    }
}
```

#### SubscriptionService

```php
<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\User;
use App\Interface\PaymentServiceInterface;
use App\Repository\SubscriptionRepository;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

final readonly class SubscriptionService implements PaymentServiceInterface
{
    private const PAYMENT_TYPE = 'subscription';
    private const STATUS_PENDING = 'pending';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_CANCELED = 'canceled';

    public function __construct(
        private string $stripeSecretKey,
        private string $stripePublicKey,
        private string $successUrl,
        private string $cancelUrl,
        private SubscriptionRepository $subscriptionRepository,
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array
    {
        try {
            // On suppose que le prix est déjà créé dans Stripe
            $priceId = $metadata['price_id'] ?? null;
            
            if (!$priceId) {
                throw new \InvalidArgumentException('Un ID de prix Stripe est requis pour créer un abonnement');
            }
            
            $session = Session::create([
                'mode' => 'subscription',
                'payment_method_types' => ['card'],
                'customer_email' => $user->getEmail(),
                'line_items' => [
                    [
                        'price' => $priceId,
                        'quantity' => 1,
                    ],
                ],
                'metadata' => array_merge($metadata, [
                    'user_id' => $user->getId()->toRfc4122(),
                ]),
                'success_url' => $this->successUrl,
                'cancel_url' => $this->cancelUrl,
            ]);

            return [
                'id' => $session->id,
                'url' => $session->url,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Erreur lors de la création de la session d\'abonnement: ' . $e->getMessage());
        }
    }

    public function handleWebhook(string $eventType, array $eventData): bool
    {
        // Logique de gestion des webhooks pour les abonnements
        // Omis pour la brièveté
    }
}
```

### 4. Decorator pour la persistance des données

```php
<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Interface\PaymentServiceInterface;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;

/**
 * Décorateur qui enrichit les services de paiement en enregistrant 
 * les transactions dans la base de données
 */
final readonly class PaymentLoggerDecorator implements PaymentServiceInterface
{
    public function __construct(
        private PaymentServiceInterface $paymentService,
        private PaymentRepository $paymentRepository,
        private SubscriptionRepository $subscriptionRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array
    {
        // Appel au service décoré pour créer la session
        $sessionData = $this->paymentService->createSession($user, $amount, $currency, $metadata);
        
        // Selon le type de service de paiement, on enregistre une entité différente
        if ($this->paymentService instanceof PaymentIntentService) {
            // Pour PaymentIntent, on enregistre un Payment
            $payment = new Payment();
            $payment->setStripeId($sessionData['id'])
                ->setAmount($amount)
                ->setCurrency($currency)
                ->setStatus('pending')
                ->setPaymentType('payment_intent')
                ->setUser($user);
            
            if (isset($metadata['description'])) {
                $payment->setDescription($metadata['description']);
            }
            
            $this->paymentRepository->save($payment);
        } elseif ($this->paymentService instanceof SubscriptionService) {
            // Pour Subscription, on enregistre un Subscription
            $subscription = new Subscription();
            $subscription->setStripeId($sessionData['id'])
                ->setStripePlanId($metadata['price_id'])
                ->setAmount($amount)
                ->setCurrency($currency)
                ->setStatus('pending')
                ->setInterval($metadata['interval'] ?? 'month')
                ->setUser($user);
            
            $this->subscriptionRepository->save($subscription);
        }
        
        return $sessionData;
    }

    public function handleWebhook(string $eventType, array $eventData): bool
    {
        // Déléguer le traitement du webhook au service décoré
        return $this->paymentService->handleWebhook($eventType, $eventData);
    }
}
```

### 5. Factory pour les Services de Paiement

```php
<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Interface\PaymentServiceInterface;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\Invoice\InvoiceService;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class PaymentServiceFactory
{
    private const TYPE_PAYMENT_INTENT = 'payment_intent';
    private const TYPE_SUBSCRIPTION = 'subscription';

    public function __construct(
        private readonly ContainerInterface $container, 
        private readonly ParameterBagInterface $params,
        private readonly PaymentRepository $paymentRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function create(string $type): PaymentServiceInterface
    {
        $baseService = match($type) {
            self::TYPE_PAYMENT_INTENT => new PaymentIntentService(
                $this->params->get('stripe_secret_key'),
                $this->paymentRepository
            ),
            self::TYPE_SUBSCRIPTION => new SubscriptionService(
                $this->params->get('stripe_secret_key'),
                $this->params->get('stripe_public_key'),
                $this->params->get('stripe_success_url'),
                $this->params->get('stripe_cancel_url'),
                $this->subscriptionRepository
            ),
            default => throw new \InvalidArgumentException("Type de service de paiement non reconnu: $type"),
        };

        // Application du décorateur à tous les services de paiement
        return new PaymentLoggerDecorator(
            $baseService,
            $this->paymentRepository,
            $this->subscriptionRepository,
            $this->userRepository
        );
    }
}
```

### 6. Controllers pour l'API de paiement et les webhooks

#### PaymentController

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\JsonRequestService;
use App\Service\Payment\PaymentServiceFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/payment', name: 'api_payment_')]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentServiceFactory $paymentServiceFactory,
        private readonly JsonRequestService $jsonRequestService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/create-payment-intent', name: 'create_payment_intent', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $data = $this->jsonRequestService->getContent($request);
        
        // Validation et création du PaymentIntent
        // Omis pour la brièveté
    }

    #[Route('/create-subscription', name: 'create_subscription', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createSubscription(Request $request): JsonResponse
    {
        $data = $this->jsonRequestService->getContent($request);
        
        // Validation et création de la Subscription
        // Omis pour la brièveté
    }
}
```

#### WebhookController

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Payment\PaymentServiceFactory;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentServiceFactory $paymentServiceFactory,
        private readonly string $stripeWebhookSecret,
        private readonly LoggerInterface $logger,
    ) {
    }
    
    #[Route('/api/webhook/stripe', name: 'api_webhook_stripe', methods: ['POST'])]
    public function handleStripeWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        
        // Vérification de la signature Stripe et traitement des événements
        // Omis pour la brièveté
    }

    private function processWebhookEvent(Event $event): void
    {
        $eventType = $event->type;
        $eventData = $event->data->object->toArray();

        // Détermination du type de service à utiliser et traitement de l'événement
        // Omis pour la brièveté
    }
}
```

### 7. Configuration 

```yaml
# config/services.yaml
parameters:
    # Stripe configuration
    stripe_secret_key: '%env(STRIPE_SECRET_KEY)%'
    stripe_public_key: '%env(STRIPE_PUBLIC_KEY)%'
    stripe_webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%'
    stripe_success_url: '%env(STRIPE_SUCCESS_URL)%'
    stripe_cancel_url: '%env(STRIPE_CANCEL_URL)%'
    
    # Payment system configuration
    payment_system_enabled: '%env(bool:PAYMENT_SYSTEM_ENABLED)%'
```

## Implémentation Frontend (Next.js)

### Composant pour les paiements ponctuels (PaymentIntent)

```tsx
import React, { useState, useEffect } from 'react';
import {
  PaymentElement,
  useStripe,
  useElements,
  Elements,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import axios from 'axios';

// Assurez-vous de charger le stripePromise avec votre clé publique
const stripePromise = loadStripe(process.env.NEXT_PUBLIC_STRIPE_PUBLIC_KEY!);

interface CheckoutFormProps {
  amount: number;
  currency?: string;
  description?: string;
}

// Composant pour le paiement unique avec Stripe Elements
export default function CheckoutForm(props: CheckoutFormProps) {
  const [clientSecret, setClientSecret] = useState<string | null>(null);

  // Création d'un PaymentIntent et affichage du formulaire de paiement
  // Omis pour la brièveté
}
```

### Composant pour les abonnements (Subscription)

```tsx
import React, { useState } from 'react';
import axios from 'axios';

interface SubscriptionFormProps {
  priceId: string;
  amount: number;
  currency?: string;
  interval?: 'month' | 'year';
  buttonText?: string;
  title: string;
  description: string;
}

export default function SubscriptionForm(props: SubscriptionFormProps) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Création d'une session d'abonnement et redirection vers Stripe Checkout
  // Omis pour la brièveté
}
```

## Utilisation

### Exemple d'utilisation du système de paiement

```php
<?php

namespace App\Controller;

use App\Service\Payment\PaymentServiceFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemoController extends AbstractController
{
    #[Route('/demo', name: 'demo')]
    public function index(PaymentServiceFactory $paymentServiceFactory): Response
    {
        // Création d'un service de paiement pour PaymentIntent
        $paymentIntentService = $paymentServiceFactory->create('payment_intent');
        
        // Création d'une session de paiement
        $sessionData = $paymentIntentService->createSession(
            $this->getUser(),
            1000, // 10€
            'eur',
            ['description' => 'Achat de produit XYZ']
        );
        
        // Création d'un service de paiement pour Subscription
        $subscriptionService = $paymentServiceFactory->create('subscription');
        
        // Création d'une session d'abonnement
        $subscriptionData = $subscriptionService->createSession(
            $this->getUser(),
            1500, // 15€/mois
            'eur',
            [
                'price_id' => 'price_abc123',
                'interval' => 'month'
            ]
        );
        
        return $this->json([
            'payment' => $sessionData,
            'subscription' => $subscriptionData,
        ]);
    }
}
```

### Désactivation du système de paiement

#### 1. Désactivation globale

Définissez la variable d'environnement dans votre fichier `.env` ou `.env.local` :

```
PAYMENT_SYSTEM_ENABLED=false
```

#### 2. Désactivation sélective avec l'attribut DisablePayment

Pour désactiver tout un contrôleur :

```php
use App\Attribute\DisablePayment;

#[DisablePayment("Fonctionnalité temporairement indisponible")]
class PaymentController extends AbstractController
{
    // ...
}
```

Pour désactiver une méthode spécifique :

```php
#[Route('/process-card', name: 'process_card')]
#[DisablePayment("Paiement par carte temporairement indisponible")]
public function processCardPayment(): Response
{
    // ...
}
```

### Script de nettoyage du système de paiement

Le projet inclut un script utilitaire pour supprimer complètement le système de paiement si nécessaire. Ce script est particulièrement utile lors du démarrage d'un nouveau projet basé sur ce boilerplate.

Localisation : `scripts/clean_payment_system.sh`

Utilisation :
```bash
cd /chemin/vers/votre/projet
./scripts/clean_payment_system.sh
```

Ce script :
1. Supprime toutes les entités liées aux paiements
2. Supprime tous les services et contrôleurs de paiement
3. Réinitialise les migrations Doctrine
4. Crée une nouvelle migration propre
5. Nettoie le cache de l'application

**Note importante** : Assurez-vous de faire une sauvegarde de vos données avant d'exécuter ce script, car il supprime définitivement les fichiers liés au système de paiement.

## Workflow complet de paiement

### Paiement ponctuel

1. Le client initie un paiement depuis le front-end Next.js via `CheckoutForm`
2. Le composant appelle l'API backend pour créer une session Checkout
3. Le backend crée la session et enregistre le paiement en "pending" via le Decorator
4. Le client est redirigé vers Stripe Checkout pour finaliser le paiement
5. Stripe envoie un webhook au backend pour confirmer le statut du paiement
6. Le WebhookController traite l'événement et met à jour le statut du paiement
7. Une facture est automatiquement générée pour le paiement

### Abonnement

1. Le client initie un abonnement depuis le front-end Next.js via `SubscriptionForm`
2. Le composant appelle l'API backend pour créer une session Stripe Checkout
3. Le backend crée la session et enregistre l'abonnement en "pending" via le Decorator
4. Le client est redirigé vers la page Stripe Checkout
5. Après le paiement, Stripe envoie un webhook au backend
6. Le WebhookController traite l'événement et met à jour le statut de l'abonnement

## Points forts de cette architecture

1. **Respect des principes SOLID**
   - Interface ségrégation avec `PaymentServiceInterface`
   - Open/Closed avec l'utilisation du Decorator
   - Dependency Inversion avec l'injection de dépendances

2. **Utilisation des design patterns**
   - Factory pour l'instanciation conditionnelle des services
   - Decorator pour enrichir les services sans les modifier
   - Strategy pour l'interchangeabilité des implémentations

3. **Architecture extensible**
   - Facile d'ajouter de nouveaux types de paiements
   - Découplage entre la logique métier et la persistance des données

4. **Gestion robuste des webhooks**
   - Vérification de signature pour sécuriser les webhooks
   - Traitement des événements avec gestion d'erreurs

5. **Système de facturation intégré**
   - Création automatique de factures pour les paiements ponctuels
   - Synchronisation avec les factures générées par Stripe pour les abonnements

6. **Flexibilité de déploiement**
   - Possibilité de désactiver le système globalement ou partiellement
   - Script de nettoyage pour démarrer un projet sans le système de paiement

## Conclusion

Cette implémentation fournit une solution complète pour intégrer Stripe dans une application Symfony, en suivant les meilleures pratiques de développement et d'architecture logicielle. Elle permet de gérer à la fois les paiements ponctuels et les abonnements récurrents, tout en offrant une API cohérente pour le front-end Next.js. Le système de désactivation et le script de nettoyage ajoutent une flexibilité supplémentaire pour adapter la solution aux besoins spécifiques de chaque projet.