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
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class PaymentIntentServiceTest extends TestCase
{
    private PaymentIntentService $paymentIntentService;
    private MockObject $stripeClientMock;
    private MockObject $paymentRepositoryMock;
    private MockObject $invoiceServiceMock;
    private MockObject $loggerMock;
    private \stdClass $mockSession;

    protected function setUp(): void
    {
        $this->stripeClientMock = $this->getMockBuilder(StripeClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();
            
        $this->paymentRepositoryMock = $this->createMock(PaymentRepository::class);
        $this->invoiceServiceMock = $this->createMock(InvoiceService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        // Create mock session response
        $this->mockSession = new \stdClass();
        $this->mockSession->id = 'cs_test_123';
        $this->mockSession->url = 'https://checkout.stripe.com/pay/cs_test_123';

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
        
        // Assign to stripe client using the magic method
        $this->stripeClientMock->method('__get')
            ->with('checkout')
            ->willReturn($checkout);

        $this->paymentIntentService = new PaymentIntentService(
            'sk_test_123',
            'https://example.com/success',
            'https://example.com/cancel',
            $this->paymentRepositoryMock,
            $this->invoiceServiceMock,
            $this->stripeClientMock,
            $this->loggerMock
        );
    }

    public function testHandleWebhookCreatesInvoiceForPaymentIntent(): void
    {
        // Créer un paiement fictif
        $payment = new Payment();
        $payment->setStatus('pending');
        $payment->setPaymentIntentId('pi_test_123');
        $payment->setAmount(1000);
        $payment->setCurrency('eur');
        $payment->setUser(new User());

        // Configurer les mocks
        $this->paymentRepositoryMock
            ->expects($this->once())
            ->method('findOneByPaymentIntentId')
            ->with('pi_test_123')
            ->willReturn($payment);

        $this->paymentRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($payment);

        $this->invoiceServiceMock
            ->expects($this->once())
            ->method('createInvoiceForPayment')
            ->with($payment, null);

        // Données de l'événement webhook
        $eventData = [
            'id' => 'pi_test_123',
            'object' => 'payment_intent',
            'amount' => 1000,
            'currency' => 'eur',
            'status' => 'succeeded',
            // Pas de champ 'invoice' ici
        ];

        // Appeler la méthode à tester
        $result = $this->paymentIntentService->handleWebhook('payment_intent.succeeded', $eventData);

        // Vérifier le résultat
        $this->assertTrue($result);
        $this->assertEquals('succeeded', $payment->getStatus());
    }

    public function testHandleWebhookCreatesInvoiceForPaymentIntentWithInvoiceId(): void
    {
        // Créer un paiement fictif
        $payment = new Payment();
        $payment->setStatus('pending');
        $payment->setPaymentIntentId('pi_test_123');
        $payment->setAmount(1000);
        $payment->setCurrency('eur');
        $payment->setUser(new User());

        // Configurer les mocks
        $this->paymentRepositoryMock
            ->expects($this->once())
            ->method('findOneByPaymentIntentId')
            ->with('pi_test_123')
            ->willReturn($payment);

        $this->paymentRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($payment);

        $this->invoiceServiceMock
            ->expects($this->once())
            ->method('createInvoiceForPayment')
            ->with($payment, 'in_test_123');

        // Données de l'événement webhook avec un invoice_id
        $eventData = [
            'id' => 'pi_test_123',
            'object' => 'payment_intent',
            'amount' => 1000,
            'currency' => 'eur',
            'status' => 'succeeded',
            'invoice' => 'in_test_123',
        ];

        // Appeler la méthode à tester
        $result = $this->paymentIntentService->handleWebhook('payment_intent.succeeded', $eventData);

        // Vérifier le résultat
        $this->assertTrue($result);
        $this->assertEquals('succeeded', $payment->getStatus());
    }

    public function testHandleCheckoutSessionCompletedCreatesInvoice(): void
    {
        // Créer un paiement fictif
        $payment = new Payment();
        $payment->setStatus('pending');
        $payment->setPaymentIntentId('pi_test_123');
        $payment->setAmount(1000);
        $payment->setCurrency('eur');
        $payment->setUser(new User());
        $payment->setCheckoutSessionId('cs_test_123');

        // Configurer les mocks
        $this->paymentRepositoryMock
            ->expects($this->once())
            ->method('findOneByCheckoutSessionId')
            ->with('cs_test_123')
            ->willReturn($payment);

        $this->paymentRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($payment);

        $this->invoiceServiceMock
            ->expects($this->once())
            ->method('createInvoiceForPayment')
            ->with($payment, null);

        // Données de l'événement webhook
        $eventData = [
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'payment_intent' => 'pi_test_123',
            'payment_status' => 'paid',
            'amount_total' => 1000,
            'currency' => 'eur',
            'mode' => 'payment',
            // Pas de champ 'invoice' ici
        ];

        // Appeler la méthode à tester
        $result = $this->paymentIntentService->handleWebhook('checkout.session.completed', $eventData);

        // Vérifier le résultat
        $this->assertTrue($result);
        $this->assertEquals('succeeded', $payment->getStatus());
    }

    public function testCreateSession(): void
    {
        // Créer un utilisateur fictif
        $user = new User();
        $user->setEmail('test@example.com');

        // Appeler la méthode à tester
        $result = $this->paymentIntentService->createSession(
            $user,
            1000,
            'eur',
            ['description' => 'Test payment']
        );

        // Vérifier le résultat
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('currency', $result);
        $this->assertEquals('cs_test_123', $result['id']);
        $this->assertEquals('https://checkout.stripe.com/pay/cs_test_123', $result['url']);
    }

    public function testHandleWebhookForUnknownEventReturnsFalse(): void
    {
        // Configurer les mocks
        $this->paymentRepositoryMock
            ->expects($this->never())
            ->method('findOneByPaymentIntentId');
            
        $this->paymentRepositoryMock
            ->expects($this->never())
            ->method('save');

        // Données de l'événement webhook
        $eventData = [
            'id' => 'unknown_id',
            'object' => 'unknown_object',
        ];

        // Appeler la méthode à tester
        $result = $this->paymentIntentService->handleWebhook('unknown.event', $eventData);

        // Vérifier le résultat
        $this->assertFalse($result);
    }
}