<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Payment;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\SubscriptionRepository;
use App\Service\Invoice\InvoiceService;
use App\Service\Payment\PaymentIntentService;
use Stripe\StripeClient;
use Symfony\Component\Uid\Uuid;

beforeEach(function () {
    $this->paymentRepository = mock(PaymentRepository::class);
    $this->invoiceRepository = mock(InvoiceRepository::class);
    $this->subscriptionRepository = mock(SubscriptionRepository::class);
    
    // Mock Stripe client
    $this->mockStripe = mock(StripeClient::class);
    $this->mockStripe->checkout = new \stdClass();
    $this->mockStripe->checkout->sessions = mock(\stdClass::class);
    $this->mockStripe->invoiceItems = mock(\stdClass::class);
    $this->mockStripe->invoices = mock(\stdClass::class);
    
    $this->invoiceService = new InvoiceService(
        'sk_test_123',
        $this->invoiceRepository,
        $this->paymentRepository,
        $this->subscriptionRepository,
        $this->mockStripe
    );
    
    $this->paymentService = new PaymentIntentService(
        'sk_test_123',
        'http://localhost/success',
        'http://localhost/cancel',
        $this->paymentRepository,
        $this->invoiceService,
        $this->mockStripe
    );
});

test('createSession crée une session de paiement valide', function () {
    $user = new User();
    $user->setEmail('test@example.com');
    $user->setId(Uuid::v4());

    $mockSession = new \stdClass();
    $mockSession->id = 'cs_test_123';
    $mockSession->url = 'https://checkout.stripe.com/pay/cs_test_123';
    
    $this->mockStripe->checkout->sessions->expects('create')
        ->andReturn($mockSession);

    $result = $this->paymentService->createSession(
        $user,
        1000,
        'eur',
        ['description' => 'Test payment']
    );

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['id', 'url', 'amount', 'currency']);
});

test('handleWebhook met à jour le statut du paiement lors d\'un succès', function () {
    $user = new User();
    $user->setEmail('test@example.com');
    $user->setStripeCustomerId('cus_123');

    $payment = new Payment();
    $payment->setStatus('pending');
    $payment->setUser($user);
    $payment->setAmount(1000);
    $payment->setCurrency('eur');
    
    $this->paymentRepository->expects('findOneByStripeId')
        ->with('cs_test_123')
        ->andReturn($payment);
    
    $this->paymentRepository->expects('save')
        ->with($payment);
        
    // Mock des appels pour la création de facture
    $this->invoiceRepository->expects('findByPayment')
        ->with($payment)
        ->andReturnNull();
        
    $mockInvoice = new \stdClass();
    $mockInvoice->id = 'in_123';
    $mockInvoice->invoice_pdf = 'https://stripe.com/invoice.pdf';
    
    $this->mockStripe->invoiceItems->expects('create')
        ->andReturn($mockInvoice);
        
    $this->mockStripe->invoices->expects('create')
        ->andReturn($mockInvoice);
        
    $this->mockStripe->invoices->expects('finalizeInvoice')
        ->andReturn($mockInvoice);
        
    $this->mockStripe->invoices->expects('pay')
        ->andReturn($mockInvoice);
    
    $this->invoiceRepository->expects('save');
        
    $success = $this->paymentService->handleWebhook(
        'checkout.session.completed',
        ['id' => 'cs_test_123', 'payment_status' => 'paid']
    );
    
    expect($success)->toBeTrue();
    expect($payment->getStatus())->toBe('succeeded');
});

test('handleWebhook marque le paiement comme échoué lors d\'une expiration', function () {
    $payment = new Payment();
    $payment->setStatus('pending');
    
    $this->paymentRepository->expects('findOneByStripeId')
        ->with('cs_test_123')
        ->andReturn($payment);
    
    $this->paymentRepository->expects('save')
        ->with($payment);
        
    $success = $this->paymentService->handleWebhook(
        'checkout.session.expired',
        ['id' => 'cs_test_123']
    );
    
    expect($success)->toBeTrue();
    expect($payment->getStatus())->toBe('failed');
});

test('handleWebhook retourne false pour un type d\'événement non géré', function () {
    $this->paymentRepository->expects('findOneByStripeId')
        ->with('cs_test_123')
        ->andReturnNull();
        
    $success = $this->paymentService->handleWebhook(
        'unknown.event',
        ['id' => 'cs_test_123']
    );
    
    expect($success)->toBeFalse();
});

test('handleWebhook crée une facture pour un paiement réussi', function () {
    $user = new User();
    $user->setStripeCustomerId('cus_123');
    
    $payment = new Payment();
    $payment->setStatus('pending');
    $payment->setUser($user);
    $payment->setAmount(1000);
    $payment->setCurrency('eur');
    
    $this->paymentRepository->expects('findOneByStripeId')
        ->with('cs_test_123')
        ->andReturn($payment);
    
    $this->paymentRepository->expects('save')
        ->with($payment);
        
    // Mock des appels pour la création de facture
    $this->invoiceRepository->expects('findByPayment')
        ->with($payment)
        ->andReturnNull();
        
    $mockInvoice = new \stdClass();
    $mockInvoice->id = 'in_123';
    $mockInvoice->invoice_pdf = 'https://stripe.com/invoice.pdf';
    
    $this->mockStripe->invoiceItems->expects('create')
        ->andReturn($mockInvoice);
        
    $this->mockStripe->invoices->expects('create')
        ->andReturn($mockInvoice);
        
    $this->mockStripe->invoices->expects('finalizeInvoice')
        ->andReturn($mockInvoice);
        
    $this->mockStripe->invoices->expects('pay')
        ->andReturn($mockInvoice);
    
    $this->invoiceRepository->expects('save');
        
    $success = $this->paymentService->handleWebhook(
        'checkout.session.completed',
        ['id' => 'cs_test_123', 'payment_status' => 'paid']
    );
    
    expect($success)->toBeTrue();
    expect($payment->getStatus())->toBe('succeeded');
});