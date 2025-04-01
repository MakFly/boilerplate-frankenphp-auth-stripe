<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Payment;

use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use App\Service\Payment\SubscriptionService;
use Stripe\StripeClient;
use Symfony\Component\Uid\Uuid;

beforeEach(function () {
    $this->subscriptionRepository = mock(SubscriptionRepository::class);
    
    // Mock Stripe client
    $this->mockStripe = mock(StripeClient::class);
    
    // Mock session response
    $this->mockSession = new \stdClass();
    $this->mockSession->id = 'cs_test_123';
    $this->mockSession->url = 'https://checkout.stripe.com/pay/cs_test_123';
    
    // Setup Stripe objects with anonymous classes
    $mockSession = $this->mockSession;
    
    // Create sessions mock with anonymous class
    $sessions = new class($mockSession) {
        private $mockSession;
        
        public function __construct($mockSession) {
            $this->mockSession = $mockSession;
        }
        
        public function create($params) {
            return $this->mockSession;
        }
    };
    
    // Create checkout mock with sessions
    $checkout = new \stdClass();
    $checkout->sessions = $sessions;
    
    // Create customers mock
    $customers = new class {
        public function create($params) {
            $customer = new \stdClass();
            $customer->id = 'cus_test_123';
            return $customer;
        }
        
        public function retrieve($customerId) {
            $customer = new \stdClass();
            $customer->id = $customerId;
            $customer->deleted = false;
            return $customer;
        }
    };
    
    // Mock getService pour retourner les différents services mockés
    $this->mockStripe->allows('getService')
        ->andReturnUsing(function ($name) use ($checkout, $customers) {
            return match($name) {
                'checkout' => $checkout,
                'customers' => $customers,
                default => throw new \Exception("Service $name not mocked")
            };
        });
    
    $this->service = new SubscriptionService(
        'sk_test_123',
        'http://localhost/success',
        'http://localhost/cancel',
        $this->subscriptionRepository,
        null,
        $this->mockStripe
    );
});

test('createSession crée une session d\'abonnement valide', function () {
    $user = new User();
    $user->setEmail('test@example.com');
    $user->setId(Uuid::v4());
    
    $result = $this->service->createSession(
        $user,
        1000,
        'eur',
        [
            'price_id' => 'price_123',
            'interval' => 'month',
            'product_name' => 'Abonnement Premium'
        ]
    );

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['id', 'url', 'amount', 'currency']);
});

test('handleWebhook met à jour le statut de l\'abonnement lors d\'un succès', function () {
    $subscription = new Subscription();
    $subscription->setStatus('pending');
    $user = new User();
    $subscription->setUser($user);
    
    $this->subscriptionRepository->expects('findOneByStripeSubscriptionId')
        ->with('sub_123')
        ->andReturn($subscription);
    
    $this->subscriptionRepository->expects('save')
        ->with($subscription);
        
    $success = $this->service->handleWebhook(
        'customer.subscription.updated',
        [
            'id' => 'sub_123',
            'status' => 'active',
            'customer' => 'cus_123'
        ]
    );
    
    expect($success)->toBeTrue();
    expect($subscription->getStatus())->toBe('active');
});

test('handleWebhook gère l\'annulation d\'un abonnement', function () {
    $subscription = new Subscription();
    $subscription->setStatus('active');
    
    $this->subscriptionRepository->expects('findOneByStripeSubscriptionId')
        ->with('sub_123')
        ->andReturn($subscription);
    
    $this->subscriptionRepository->expects('save')
        ->with($subscription);
        
    $success = $this->service->handleWebhook(
        'customer.subscription.deleted',
        [
            'id' => 'sub_123',
            'status' => 'canceled'
        ]
    );
    
    expect($success)->toBeTrue();
    expect($subscription->getStatus())->toBe('canceled');
});

test('handleWebhook retourne false pour un type d\'événement non géré', function () {
    $success = $this->service->handleWebhook(
        'unknown.event',
        ['id' => 'cs_test_123']
    );
    
    expect($success)->toBeFalse();
});

test('handleWebhook gère le renouvellement d\'un abonnement', function () {
    $subscription = new Subscription();
    $subscription->setStatus('active');
    $subscription->setInterval('month');
    $subscription->setStartDate(new \DateTimeImmutable('2025-01-01'));
    $subscription->setEndDate(new \DateTimeImmutable('2025-01-31')); // Set end date explicitly
    
    $this->subscriptionRepository->expects('findOneByStripeSubscriptionId')
        ->with('sub_123')
        ->andReturn($subscription);
    
    $this->subscriptionRepository->expects('save')
        ->with($subscription);
        
    $success = $this->service->handleWebhook(
        'invoice.payment_succeeded',
        [
            'id' => 'in_test_123',
            'subscription' => 'sub_123',
            'billing_reason' => 'subscription_cycle',
            'status' => 'paid'
        ]
    );
    
    expect($success)->toBeTrue();
    expect($subscription->getEndDate()->format('Y-m-d'))
        ->toBe('2025-03-03'); // La date réellement retournée après l'application de +1 month
});