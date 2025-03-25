<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\User;
use App\Interface\PaymentServiceInterface;
use App\Repository\PaymentRepository;
use App\Service\Invoice\InvoiceService;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

final readonly class PaymentIntentService implements PaymentServiceInterface
{
    private const PAYMENT_TYPE = 'payment_intent';
    private const STATUS_PENDING = 'pending';
    private const STATUS_SUCCEEDED = 'succeeded';
    private const STATUS_FAILED = 'failed';
    
    private StripeClient $stripe;
    
    public function __construct(
        private string $stripeSecretKey,
        private string $successUrl,
        private string $cancelUrl,
        private PaymentRepository $paymentRepository,
        private ?InvoiceService $invoiceService = null,
        ?StripeClient $stripeClient = null
    ) {
        $this->stripe = $stripeClient ?? new StripeClient($this->stripeSecretKey);
    }

    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array
    {
        try {
            // Création d'une session Stripe Checkout en mode payment
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'customer_email' => $user->getEmail(),
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => [
                                'name' => $metadata['product_name'] ?? 'Paiement',
                                'description' => $metadata['description'] ?? null,
                            ],
                            'unit_amount' => $amount, // Montant en centimes
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'metadata' => array_merge($metadata, [
                    'user_id' => $user->getId(),
                ]),
                'success_url' => $this->successUrl,
                'cancel_url' => $this->cancelUrl,
            ]);
            
            return [
                'id' => $session->id,
                'url' => $session->url,
                'amount' => $amount,
                'currency' => $currency,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Erreur lors de la création de la session de paiement: ' . $e->getMessage());
        }
    }

    /**
     * @param array{id?: string} $eventData Les données de l'événement webhook
     */
    public function handleWebhook(string $eventType, array $eventData): bool
    {
        $sessionId = $eventData['id'] ?? null;
        
        if (!$sessionId) {
            return false;
        }
        
        $payment = $this->paymentRepository->findOneByStripeId($sessionId);
        
        if (!$payment) {
            return false;
        }
        
        switch ($eventType) {
            case 'checkout.session.completed':
                $payment->setStatus(self::STATUS_SUCCEEDED);
                $this->paymentRepository->save($payment);
                
                // Créer une facture si le service de facturation est disponible
                if ($this->invoiceService !== null) {
                    $this->invoiceService->createInvoiceForPayment($payment);
                }
                return true;
                
            case 'checkout.session.expired':
                $payment->setStatus(self::STATUS_FAILED);
                $this->paymentRepository->save($payment);
                return true;
                
            default:
                return false;
        }
    }
    
    /**
     * Crée une facture pour un paiement réussi
     */
    private function createInvoiceForPayment(Payment $payment): void
    {
        // Vérifier que le service de facturation est disponible
        if ($this->invoiceService === null) {
            return;
        }
        
        try {
            // Créer une facture uniquement pour les paiements réussis
            if ($payment->getStatus() === self::STATUS_SUCCEEDED) {
                $this->invoiceService->createInvoiceForPayment($payment);
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas bloquer le processus
            // Idéalement, il faudrait utiliser un logger ici
        }
    }
}