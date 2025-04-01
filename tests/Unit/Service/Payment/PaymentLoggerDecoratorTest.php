<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Payment;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Interface\PaymentServiceInterface;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\Payment\PaymentIntentService;
use App\Service\Payment\PaymentLoggerDecorator;
use App\Service\Payment\SubscriptionService;
use Stripe\StripeClient;
use Symfony\Component\Uid\Uuid;

beforeEach(function () {
    $this->paymentRepository = mock(PaymentRepository::class);
    $this->subscriptionRepository = mock(SubscriptionRepository::class);
    $this->userRepository = mock(UserRepository::class);
    
    // Mock Stripe
    $this->mockStripe = mock(StripeClient::class);
    
    // Mock session response
    $this->mockSession = new \stdClass();
    $this->mockSession->id = 'cs_test_123';
    $this->mockSession->url = 'https://checkout.stripe.com/123';
    
    // Setup Stripe objects with anonymous classes
    $mockSession = $this->mockSession;
    
    // Create sessions mock
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
});

test('createSession enregistre un paiement lors d\'un PaymentIntent', function () {
    $user = new User();
    $user->setEmail('test@example.com');
    $user->setId(Uuid::v4());
    
    $paymentService = new PaymentIntentService(
        'sk_test_123',
        'http://localhost/success',
        'http://localhost/cancel',
        $this->paymentRepository,
        null,
        $this->mockStripe
    );
    
    $decorator = new PaymentLoggerDecorator(
        $paymentService,
        $this->paymentRepository,
        $this->subscriptionRepository,
        $this->userRepository
    );
    
    $this->paymentRepository->expects('save')
        ->withArgs(function($payment) {
            return $payment instanceof Payment
                && $payment->getStripeId() === 'cs_test_123'
                && $payment->getAmount() === 1000
                && $payment->getCurrency() === 'eur'
                && $payment->getStatus() === 'pending'
                && $payment->getPaymentType() === 'payment_intent';
        });
    
    $result = $decorator->createSession(
        $user,
        1000,
        'eur',
        ['description' => 'Test payment']
    );
    
    expect($result)
        ->toBeArray()
        ->toHaveKeys(['id', 'url', 'amount', 'currency']);
});

test('createSession enregistre un abonnement lors d\'une Subscription', function () {
    $user = new User();
    $user->setEmail('test@example.com');
    $user->setId(Uuid::v4());
    $user->setStripeCustomerId('cus_existing_123');
    
    $subscriptionService = new SubscriptionService(
        'sk_test_123',
        'http://localhost/success',
        'http://localhost/cancel',
        $this->subscriptionRepository,
        null,
        $this->mockStripe,
        null,
        $this->userRepository
    );
    
    $decorator = new PaymentLoggerDecorator(
        $subscriptionService,
        $this->paymentRepository,
        $this->subscriptionRepository,
        $this->userRepository
    );
    
    $this->subscriptionRepository->expects('save')
        ->withArgs(function($subscription) {
            return $subscription instanceof Subscription
                && $subscription->getStripeId() === 'cs_test_123'
                && $subscription->getAmount() === 1000
                && $subscription->getCurrency() === 'eur'
                && $subscription->getStatus() === 'pending'
                && $subscription->getInterval() === 'month';
        });
    
    $result = $decorator->createSession(
        $user,
        1000,
        'eur',
        [
            'price_id' => 'price_123',
            'interval' => 'month'
        ]
    );
    
    expect($result)
        ->toBeArray()
        ->toHaveKeys(['id', 'url', 'amount', 'currency']);
});

test('handleWebhook délègue le traitement au service décoré', function () {
    $paymentService = mock(PaymentServiceInterface::class);
    
    $decorator = new PaymentLoggerDecorator(
        $paymentService,
        $this->paymentRepository,
        $this->subscriptionRepository,
        $this->userRepository
    );
    
    $paymentService->expects('handleWebhook')
        ->with('checkout.session.completed', ['id' => 'cs_test_123'])
        ->andReturn(true);
        
    $result = $decorator->handleWebhook(
        'checkout.session.completed',
        ['id' => 'cs_test_123']
    );
    
    expect($result)->toBeTrue();
});