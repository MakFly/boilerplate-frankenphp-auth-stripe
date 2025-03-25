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
    $this->mockStripe->checkout = new \stdClass();
    $this->mockStripe->checkout->sessions = mock(\stdClass::class);
    
    $this->service = new SubscriptionService(
        'sk_test_123',
        'pk_test_123',
        'http://localhost/success',
        'http://localhost/cancel',
        $this->subscriptionRepository,
        $this->mockStripe
    );
});

test('createSession crée une session d\'abonnement valide', function () {
    $user = new User();
    $user->setEmail('test@example.com');
    $user->setId(Uuid::v4());
    
    $mockSession = new \stdClass();
    $mockSession->id = 'cs_test_123';
    $mockSession->url = 'https://checkout.stripe.com/pay/cs_test_123';
    
    $this->mockStripe->checkout->sessions->expects('create')
        ->andReturn($mockSession);

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
    
    $this->subscriptionRepository->expects('findOneByStripeId')
        ->with('sub_123')
        ->andReturn($subscription);
    
    $this->subscriptionRepository->expects('save')
        ->with($subscription);
        
    $success = $this->service->handleWebhook(
        'checkout.session.completed',
        [
            'subscription' => 'sub_123',
            'customer' => 'cus_123'
        ]
    );
    
    expect($success)->toBeTrue();
    expect($subscription->getStatus())->toBe('active');
    expect($user->getStripeCustomerId())->toBe('cus_123');
});

test('handleWebhook gère l\'annulation d\'un abonnement', function () {
    $subscription = new Subscription();
    $subscription->setStatus('active');
    
    $this->subscriptionRepository->expects('findOneByStripeId')
        ->with('sub_123')
        ->andReturn($subscription);
    
    $this->subscriptionRepository->expects('save')
        ->with($subscription);
        
    $success = $this->service->handleWebhook(
        'customer.subscription.deleted',
        [
            'subscription' => 'sub_123'
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
    
    $this->subscriptionRepository->expects('findOneByStripeId')
        ->with('sub_123')
        ->andReturn($subscription);
    
    $this->subscriptionRepository->expects('save')
        ->with($subscription);
        
    $success = $this->service->handleWebhook(
        'invoice.payment_succeeded',
        [
            'subscription' => 'sub_123',
            'billing_reason' => 'subscription_cycle'
        ]
    );
    
    expect($success)->toBeTrue();
    expect($subscription->getEndDate()->format('Y-m-d'))
        ->toBe('2025-02-01'); // Un mois après la dernière date de fin
});