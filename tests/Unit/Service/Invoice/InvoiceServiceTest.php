<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Service\Invoice\InvoiceService;
use Stripe\StripeClient;
use Symfony\Component\Uid\Uuid;

beforeEach(function () {
    $this->invoiceRepository = mock(InvoiceRepository::class);
    $this->paymentRepository = mock(PaymentRepository::class);
    $this->subscriptionRepository = mock(SubscriptionRepository::class);
    
    // Mock Stripe
    $this->mockStripe = mock(StripeClient::class);
    
    // Mock invoice item response
    $this->mockInvoiceItem = new \stdClass();
    $this->mockInvoiceItem->id = 'ii_123';
    
    // Mock invoice response
    $this->mockInvoice = new \stdClass();
    $this->mockInvoice->id = 'in_123';
    $this->mockInvoice->invoice_pdf = 'https://stripe.com/invoice.pdf';
    $this->mockInvoice->amount_due = 1000;
    $this->mockInvoice->currency = 'eur';
    $this->mockInvoice->amount_paid = 1000;
    $this->mockInvoice->status = 'paid';
    
    // Setup Stripe objects with anonymous classes
    $mockInvoiceItem = $this->mockInvoiceItem;
    $mockInvoice = $this->mockInvoice;
    
    $this->mockStripe->invoiceItems = new class($mockInvoiceItem) {
        private $mockInvoiceItem;
        
        public function __construct($mockInvoiceItem) {
            $this->mockInvoiceItem = $mockInvoiceItem;
        }
        
        public function create($params) {
            return $this->mockInvoiceItem;
        }
    };
    
    $this->mockStripe->invoices = new class($mockInvoice) {
        private $mockInvoice;
        
        public function __construct($mockInvoice) {
            $this->mockInvoice = $mockInvoice;
        }
        
        public function create($params) {
            return $this->mockInvoice;
        }
        
        public function finalizeInvoice($id, $params = []) {
            return $this->mockInvoice;
        }
        
        public function pay($id, $params = []) {
            return $this->mockInvoice;
        }
        
        public function retrieve($id) {
            return $this->mockInvoice;
        }
    };
    
    $this->invoiceService = new InvoiceService(
        'sk_test_123',
        $this->invoiceRepository,
        $this->paymentRepository,
        $this->subscriptionRepository,
        $this->mockStripe
    );
});

test('createInvoiceForPayment crée une facture pour un nouveau paiement', function () {
    $user = new User();
    $user->setEmail('test@example.com');
    $user->setId(Uuid::v4());
    $user->setStripeCustomerId('cus_123');
    
    $payment = new Payment();
    $payment->setUser($user);
    $payment->setAmount(1000);
    $payment->setCurrency('eur');
    $payment->setDescription('Test payment');
    
    $this->invoiceRepository->expects('findByPayment')
        ->with($payment)
        ->andReturnNull();
    
    $this->invoiceRepository->expects('save')
        ->withAnyArgs();
    
    $invoice = $this->invoiceService->createInvoiceForPayment($payment);
    
    expect($invoice)
        ->toBeInstanceOf(Invoice::class)
        ->and($invoice->getStripeInvoiceId())->toBe('in_123')
        ->and($invoice->getAmount())->toBe(1000)
        ->and($invoice->getCurrency())->toBe('eur');
});

test('createInvoiceForPayment retourne la facture existante si elle existe', function () {
    $payment = new Payment();
    $existingInvoice = new Invoice();
    
    $this->invoiceRepository->expects('findByPayment')
        ->with($payment)
        ->andReturn($existingInvoice);
        
    $invoice = $this->invoiceService->createInvoiceForPayment($payment);
    
    expect($invoice)->toBe($existingInvoice);
});

test('createInvoiceForPayment échoue si l\'utilisateur n\'a pas de Stripe Customer ID', function () {
    $user = new User();
    $user->setStripeCustomerId(null);
    
    $payment = new Payment();
    $payment->setUser($user);
    
    $this->invoiceRepository->expects('findByPayment')
        ->with($payment)
        ->andReturnNull();
    
    expect(fn() => $this->invoiceService->createInvoiceForPayment($payment))
        ->toThrow(\RuntimeException::class, 'L\'utilisateur doit avoir un customer ID Stripe pour créer une facture.');
});