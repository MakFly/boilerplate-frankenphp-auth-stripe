<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\User;
use App\Interface\PaymentServiceInterface;
use App\Repository\PaymentRepository;
use App\Service\Invoice\InvoiceService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PaymentIntentService implements PaymentServiceInterface
{
    private StripeClient $stripe;

    public function __construct(
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private string $stripeSecretKey,
        #[Autowire(env: 'STRIPE_SUCCESS_URL')]
        private string $successUrl,
        #[Autowire(env: 'STRIPE_CANCEL_URL')]
        private string $cancelUrl,
        private PaymentRepository $paymentRepository,
        private ?InvoiceService $invoiceService = null,
        ?StripeClient $stripeClient = null,
        private ?LoggerInterface $logger = null
    ) {
        $this->stripe = $stripeClient ?? new StripeClient($this->stripeSecretKey);
    }

    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array
    {
        $this->logSessionCreation($user, $amount, $currency);

        try {
            $session = $this->createStripeSession($user, $amount, $currency, $metadata);
            $this->logSessionSuccess($session);
            
            return $this->formatSessionResponse($session, $amount, $currency);
        } catch (ApiErrorException $e) {
            $this->logSessionError($e, $user);
            throw new \RuntimeException('Erreur lors de la création de la session de paiement: ' . $e->getMessage());
        }
    }

    private function logSessionCreation(User $user, int $amount, string $currency): void
    {
        $this->logger?->info('Création d\'une session de paiement', [
            'user_id' => $user->getId(),
            'amount' => $amount,
            'currency' => $currency
        ]);
    }

    private function createStripeSession(User $user, int $amount, string $currency, array $metadata): object
    {
        return $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'customer_email' => $user->getEmail(),
            'line_items' => $this->buildLineItems($amount, $currency, $metadata),
            'mode' => 'payment',
            'metadata' => $this->buildMetadata($metadata, $user),
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
        ]);
    }

    private function buildLineItems(int $amount, string $currency, array $metadata): array
    {
        return [
            [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => $metadata['product_name'] ?? 'Paiement',
                        'description' => $metadata['description'] ?? null,
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => 1,
            ],
        ];
    }

    private function buildMetadata(array $metadata, User $user): array
    {
        return array_merge($metadata, [
            'user_id' => $user->getId(),
        ]);
    }

    private function logSessionSuccess(object $session): void
    {
        $this->logger?->debug('Session de paiement créée', [
            'session_id' => $session->id,
            'url' => $session->url
        ]);
    }

    private function formatSessionResponse(object $session, int $amount, string $currency): array
    {
        return [
            'id' => $session->id,
            'url' => $session->url,
            'amount' => $amount,
            'currency' => $currency,
        ];
    }

    private function logSessionError(ApiErrorException $e, User $user): void
    {
        $this->logger?->error('Erreur lors de la création de la session de paiement', [
            'error' => $e->getMessage(),
            'user_id' => $user->getId()
        ]);
    }

    /**
     * Gère un événement webhook pour les paiements
     * 
     * @param string $eventType Le type d'événement webhook
     * @param array<string, mixed> $eventData Les données de l'événement webhook
     * @return bool Succès ou échec du traitement
     */
    public function handleWebhook(string $eventType, array $eventData): bool
    {
        $this->logWebhookProcessing($eventType, $eventData);

        $payment = $this->processWebhookEvent($eventType, $eventData);

        return $payment !== null;
    }

    private function logWebhookProcessing(string $eventType, array $eventData): void
    {
        $this->logger?->info('Traitement d\'un webhook pour payment intent', [
            'event_type' => $eventType,
            'data' => $eventData,
        ]);
    }

    private function processWebhookEvent(string $eventType, array $eventData): ?Payment
    {
        return match ($eventType) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($eventData),
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($eventData),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($eventData),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($eventData),
            default => null,
        };
    }

    /**
     * Gère un événement de finalisation de session de checkout
     * @param array<string, mixed> $eventData
     */
    private function handleCheckoutSessionCompleted(array $eventData): ?Payment
    {
        $sessionId = $eventData['id'] ?? null;
        $paymentIntentId = $eventData['payment_intent'] ?? null;

        if (!$this->validateCheckoutSessionData($sessionId, $paymentIntentId)) {
            return null;
        }

        $payment = $this->findPaymentBySessionOrIntent($sessionId, $paymentIntentId);
        if (!$payment) {
            return null;
        }

        $this->updatePaymentIntentId($payment, $paymentIntentId);
        $this->updatePaymentStatus($payment, $eventData);
        $this->tryCreateInvoice($payment, $eventData);

        $this->paymentRepository->save($payment);
        return $payment;
    }

    private function validateCheckoutSessionData(?string $sessionId, ?string $paymentIntentId): bool
    {
        if (!$sessionId || !$paymentIntentId) {
            $this->logger?->warning('Webhook checkout.session.completed sans session ID ou payment intent ID', [
                'session_id' => $sessionId,
                'payment_intent_id' => $paymentIntentId,
            ]);
            return false;
        }
        return true;
    }

    private function findPaymentBySessionOrIntent(string $sessionId, string $paymentIntentId): ?Payment
    {
        $payment = $this->paymentRepository->findOneByCheckoutSessionId($sessionId);

        if (!$payment) {
            $this->logger?->warning('Aucun paiement trouvé pour la session de checkout', [
                'session_id' => $sessionId
            ]);

            $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);

            if (!$payment) {
                $this->logger?->warning('Aucun paiement trouvé pour le payment intent', [
                    'payment_intent_id' => $paymentIntentId
                ]);
                return null;
            }
        }

        return $payment;
    }

    private function updatePaymentIntentId(Payment $payment, string $paymentIntentId): void
    {
        if (!$payment->getPaymentIntentId()) {
            $payment->setPaymentIntentId($paymentIntentId);
        }
    }

    private function updatePaymentStatus(Payment $payment, array $eventData): void
    {
        $paymentStatus = $eventData['payment_status'] ?? null;
        $status = ($paymentStatus === 'paid') ? 'succeeded' : 'pending';
        $payment->setStatus($status);
    }

    private function tryCreateInvoice(Payment $payment, array $eventData): void
    {
        if (!$this->invoiceService || $payment->getStatus() !== 'succeeded') {
            return;
        }

        try {
            $stripeInvoiceId = $eventData['invoice'] ?? null;
            $this->invoiceService->createInvoiceForPayment($payment, $stripeInvoiceId);
            $this->logger?->info('Facture créée pour le paiement', [
                'payment_id' => $payment->getId()->toRfc4122(),
                'stripe_invoice_id' => $stripeInvoiceId,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors de la création de la facture pour le paiement', [
                'payment_id' => $payment->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gère un événement de réussite de payment intent
     * @param array<string, mixed> $eventData
     */
    private function handlePaymentIntentSucceeded(array $eventData): ?Payment
    {
        $paymentIntentId = $eventData['id'] ?? null;

        if (!$paymentIntentId) {
            $this->logger?->warning('Webhook payment_intent.succeeded sans payment intent ID');
            return null;
        }

        $payment = $this->findPaymentByIntentId($paymentIntentId);
        if (!$payment) {
            return null;
        }

        $this->updatePaymentToSucceeded($payment, $paymentIntentId);
        $this->tryCreateInvoice($payment, $eventData);

        return $payment;
    }

    private function findPaymentByIntentId(string $paymentIntentId): ?Payment
    {
        $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            $this->logger?->warning('Aucun paiement trouvé pour le payment intent', [
                'payment_intent_id' => $paymentIntentId
            ]);
        }

        return $payment;
    }

    private function updatePaymentToSucceeded(Payment $payment, string $paymentIntentId): void
    {
        $payment->setStatus('succeeded');
        $this->paymentRepository->save($payment);

        $this->logger?->info('Paiement marqué comme réussi', [
            'payment_id' => $payment->getId()->toRfc4122(),
            'payment_intent_id' => $paymentIntentId,
        ]);
    }

    /**
     * Gère un événement d'échec de payment intent
     * @param array<string, mixed> $eventData
     */
    private function handlePaymentIntentFailed(array $eventData): ?Payment
    {
        $paymentIntentId = $eventData['id'] ?? null;

        if (!$paymentIntentId) {
            $this->logger->warning('Webhook payment_intent.payment_failed sans payment intent ID');
            return null;
        }

        // Rechercher le paiement associé à ce payment intent
        $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            $this->logger->warning('Aucun paiement trouvé pour le payment intent en échec', [
                'payment_intent_id' => $paymentIntentId
            ]);
            return null;
        }

        // Mettre à jour le statut
        $payment->setStatus('failed');
        $this->paymentRepository->save($payment);

        $this->logger->info('Paiement marqué comme échoué', [
            'payment_id' => $payment->getId()->toRfc4122(),
            'payment_intent_id' => $paymentIntentId,
        ]);

        return $payment;
    }

    /**
     * Gère un événement de réussite de paiement de facture
     * @param array<string, mixed> $eventData
     */
    private function handleInvoicePaymentSucceeded(array $eventData): ?Payment
    {
        $invoiceId = $eventData['id'] ?? null;
        $paymentIntentId = $eventData['payment_intent'] ?? null;

        if (!$invoiceId || !is_string($invoiceId)) {
            $this->logger->warning('Webhook invoice.payment_succeeded sans invoice ID');
            return null;
        }

        // S'il n'y a pas de payment intent (peut arriver pour les abonnements), retourner null
        if (!$paymentIntentId) {
            return null;
        }

        // Rechercher le paiement associé à ce payment intent
        $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            $this->logger->warning('Aucun paiement trouvé pour le payment intent de la facture', [
                'payment_intent_id' => $paymentIntentId,
                'invoice_id' => $invoiceId,
            ]);
            return null;
        }

        // Mettre à jour le statut si nécessaire
        if ($payment->getStatus() !== 'succeeded') {
            $payment->setStatus('succeeded');
            $this->paymentRepository->save($payment);

            $this->logger->info('Paiement marqué comme réussi via invoice.payment_succeeded', [
                'payment_id' => $payment->getId()->toRfc4122(),
                'payment_intent_id' => $paymentIntentId,
                'invoice_id' => $invoiceId,
            ]);
        }

        // Créer une facture pour ce paiement si nécessaire
        if ($this->invoiceService) {
            try {
                $this->invoiceService->createInvoiceForPayment($payment, $invoiceId);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la création de la facture pour le paiement', [
                    'payment_id' => $payment->getId()->toRfc4122(),
                    'invoice_id' => $invoiceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment;
    }
}
