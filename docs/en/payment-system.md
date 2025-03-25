# Documentation: Stripe Payment System for Symfony

## Introduction

This document presents the implementation of a complete payment system using Stripe with Symfony, based on Factory and Decorator patterns. This architecture allows easy handling of different types of payments (one-time via Payment Intents and recurring via Subscriptions) while respecting SOLID principles and ensuring maximum extensibility.

## System Architecture

The system is structured around several key components:

1. **PaymentServiceInterface**: Defines the contract for all payment services
2. **Specific implementations**: Concrete services for each payment type
3. **PaymentLoggerDecorator**: Enriches services with data persistence
4. **PaymentServiceFactory**: Dynamically creates the appropriate service based on type
5. **Webhooks**: Handling Stripe events to update statuses
6. **Invoice system**: Automatic invoice generation for one-time payments and subscriptions
7. **Deactivation mechanism**: Ability to disable the payment system globally or specifically

### Class Diagram

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

## Implementation

### 1. PaymentServiceInterface

```php
<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

interface PaymentServiceInterface
{
    /**
     * Creates a payment session with Stripe
     * 
     * @param User $user User making the payment
     * @param int $amount Amount in cents
     * @param string $currency Currency (e.g., 'usd')
     * @param array $metadata Additional metadata
     * @return array Session information with necessary keys for front-end
     */
    public function createSession(User $user, int $amount, string $currency = 'usd', array $metadata = []): array;
    
    /**
     * Processes a Stripe webhook based on event type
     * 
     * @param string $eventType Stripe event type
     * @param array $eventData Event data
     * @return bool Success or failure of processing
     */
    public function handleWebhook(string $eventType, array $eventData): bool;
}
```

### 2. Entities for Data Persistence

#### Payment Entity

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

    // Getters, setters and constructor omitted for brevity
}
```

#### Subscription Entity

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

    // Getters, setters and constructor omitted for brevity
}
```

### 3. Payment Service Implementations

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

    public function createSession(User $user, int $amount, string $currency = 'usd', array $metadata = []): array
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
            throw new \RuntimeException('Error creating PaymentIntent: ' . $e->getMessage());
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

    public function createSession(User $user, int $amount, string $currency = 'usd', array $metadata = []): array
    {
        try {
            // Assuming price is already created in Stripe
            $priceId = $metadata['price_id'] ?? null;
            
            if (!$priceId) {
                throw new \InvalidArgumentException('A Stripe price ID is required to create a subscription');
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
            throw new \RuntimeException('Error creating subscription session: ' . $e->getMessage());
        }
    }

    public function handleWebhook(string $eventType, array $eventData): bool
    {
        // Webhook handling logic for subscriptions
        // Omitted for brevity
    }
}
```

### 4. Decorator for Data Persistence

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
 * Decorator that enriches payment services by recording 
 * transactions in the database
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

    public function createSession(User $user, int $amount, string $currency = 'usd', array $metadata = []): array
    {
        // Call decorated service to create session
        $sessionData = $this->paymentService->createSession($user, $amount, $currency, $metadata);
        
        // Based on payment service type, record different entity
        if ($this->paymentService instanceof PaymentIntentService) {
            // For PaymentIntent, record a Payment
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
            // For Subscription, record a Subscription
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
        // Delegate webhook handling to decorated service
        return $this->paymentService->handleWebhook($eventType, $eventData);
    }
}
```

### 5. Payment Service Factory

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
            default => throw new \InvalidArgumentException("Unrecognized payment service type: $type"),
        };

        // Apply decorator to all payment services
        return new PaymentLoggerDecorator(
            $baseService,
            $this->paymentRepository,
            $this->subscriptionRepository,
            $this->userRepository
        );
    }
}
```

### 6. Controllers for Payment API and Webhooks

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
        
        // Validation and PaymentIntent creation
        // Omitted for brevity
    }

    #[Route('/create-subscription', name: 'create_subscription', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createSubscription(Request $request): JsonResponse
    {
        $data = $this->jsonRequestService->getContent($request);
        
        // Validation and Subscription creation
        // Omitted for brevity
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
        
        // Stripe signature verification and event processing
        // Omitted for brevity
    }

    private function processWebhookEvent(Event $event): void
    {
        $eventType = $event->type;
        $eventData = $event->data->object->toArray();

        // Determine service type and process event
        // Omitted for brevity
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

## Front-end Implementation (Next.js)

### Component for One-time Payments (PaymentIntent)

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

// Make sure to load stripePromise with your public key
const stripePromise = loadStripe(process.env.NEXT_PUBLIC_STRIPE_PUBLIC_KEY!);

interface CheckoutFormProps {
  amount: number;
  currency?: string;
  description?: string;
}

// Component for single payment with Stripe Elements
export default function CheckoutForm(props: CheckoutFormProps) {
  const [clientSecret, setClientSecret] = useState<string | null>(null);

  // Create PaymentIntent and display payment form
  // Omitted for brevity
}
```

### Component for Subscriptions

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

  // Create subscription session and redirect to Stripe Checkout
  // Omitted for brevity
}
```

## Usage

### Example of Payment System Usage

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
        // Create payment service for PaymentIntent
        $paymentIntentService = $paymentServiceFactory->create('payment_intent');
        
        // Create payment session
        $sessionData = $paymentIntentService->createSession(
            $this->getUser(),
            1000, // $10
            'usd',
            ['description' => 'Product XYZ purchase']
        );
        
        // Create payment service for Subscription
        $subscriptionService = $paymentServiceFactory->create('subscription');
        
        // Create subscription session
        $subscriptionData = $subscriptionService->createSession(
            $this->getUser(),
            1500, // $15/month
            'usd',
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

### Disabling the Payment System

#### 1. Global Deactivation

Set the environment variable in your `.env` or `.env.local` file:

```
PAYMENT_SYSTEM_ENABLED=false
```

#### 2. Selective Deactivation with DisablePayment Attribute

To disable an entire controller:

```php
use App\Attribute\DisablePayment;

#[DisablePayment("Feature temporarily unavailable")]
class PaymentController extends AbstractController
{
    // ...
}
```

To disable a specific method:

```php
#[Route('/process-card', name: 'process_card')]
#[DisablePayment("Card payment temporarily unavailable")]
public function processCardPayment(): Response
{
    // ...
}
```

### Payment System Cleanup Script

The project includes a utility script to completely remove the payment system if needed. This script is particularly useful when starting a new project based on this boilerplate.

Location: `scripts/clean_payment_system.sh`

Usage:
```bash
cd /path/to/your/project
./scripts/clean_payment_system.sh
```

This script:
1. Removes all payment-related entities
2. Removes all payment services and controllers
3. Resets Doctrine migrations
4. Creates a clean new migration
5. Cleans application cache

**Important note**: Make sure to backup your data before running this script, as it permanently deletes payment system-related files.

## Complete Payment Workflow

### One-time Payment

1. Client initiates payment from Next.js frontend via `CheckoutForm`
2. Component calls backend API to create Checkout session
3. Backend creates session and records payment as "pending" via Decorator
4. Client is redirected to Stripe Checkout to complete payment
5. Stripe sends webhook to backend to confirm payment status
6. WebhookController processes the event and updates payment status
7. An invoice is automatically generated for the payment

### Subscription

1. Client initiates subscription from Next.js frontend via `SubscriptionForm`
2. Component calls backend API to create Stripe Checkout session
3. Backend creates session and records subscription as "pending" via Decorator
4. Client is redirected to Stripe Checkout page
5. After payment, Stripe sends webhook to backend
6. WebhookController processes event and updates subscription status

## Architecture Strengths

1. **SOLID Principles Respect**
   - Interface segregation with `PaymentServiceInterface`
   - Open/Closed with Decorator usage
   - Dependency Inversion with dependency injection

2. **Design Pattern Usage**
   - Factory for conditional service instantiation
   - Decorator to enrich services without modification
   - Strategy for interchangeable implementations

3. **Extensible Architecture**
   - Easy to add new payment types
   - Decoupling between business logic and data persistence

4. **Robust Webhook Handling**
   - Signature verification to secure webhooks
   - Event handling with error management

5. **Integrated Billing System**
   - Automatic invoice creation for one-time payments
   - Synchronization with Stripe-generated invoices for subscriptions

6. **Deployment Flexibility**
   - Ability to disable system globally or partially
   - Cleanup script to start project without payment system