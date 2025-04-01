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
        try {
            $this->logger?->info('Création d\'une session de paiement', [
                'user_id' => $user->getId(),
                'amount' => $amount,
                'currency' => $currency
            ]);

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

            $this->logger?->debug('Session de paiement créée', [
                'session_id' => $session->id,
                'url' => $session->url
            ]);

            return [
                'id' => $session->id,
                'url' => $session->url,
                'amount' => $amount,
                'currency' => $currency,
            ];
        } catch (ApiErrorException $e) {
            $this->logger?->error('Erreur lors de la création de la session de paiement', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);
            throw new \RuntimeException('Erreur lors de la création de la session de paiement: ' . $e->getMessage());
        }
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
        $this->logger->info('Traitement d\'un webhook pour payment intent', [
            'event_type' => $eventType,
            'data' => $eventData,
        ]);

        $payment = null;

        // Traiter l'événement avec la méthode appropriée
        $payment = match ($eventType) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($eventData),
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($eventData),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($eventData),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($eventData),
            default => null,
        };

        // Si on a réussi à traiter le paiement, retourner true
        return $payment !== null;
    }

    /**
     * Gère un événement de finalisation de session de checkout
     * @param array<string, mixed> $eventData
     */
    private function handleCheckoutSessionCompleted(array $eventData): ?Payment
    {
        $sessionId = $eventData['id'] ?? null;
        $paymentIntentId = $eventData['payment_intent'] ?? null;

        if (!$sessionId || !$paymentIntentId) {
            $this->logger->warning('Webhook checkout.session.completed sans session ID ou payment intent ID', [
                'session_id' => $sessionId,
                'payment_intent_id' => $paymentIntentId,
            ]);
            return null;
        }

        // Vérifier si nous avons déjà un paiement associé à cette session
        $payment = $this->paymentRepository->findOneByCheckoutSessionId($sessionId);

        if (!$payment) {
            $this->logger->warning('Aucun paiement trouvé pour la session de checkout', [
                'session_id' => $sessionId
            ]);

            // Essayer de trouver par payment_intent_id
            $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);

            if (!$payment) {
                $this->logger->warning('Aucun paiement trouvé pour le payment intent', [
                    'payment_intent_id' => $paymentIntentId
                ]);
                return null;
            }
        }

        // Mettre à jour le payment intent ID si nécessaire
        if (!$payment->getPaymentIntentId()) {
            $payment->setPaymentIntentId($paymentIntentId);
        }

        // Mettre à jour le statut du paiement en fonction de l'état de la session
        $paymentStatus = $eventData['payment_status'] ?? null;
        if ($paymentStatus === 'paid') {
            $payment->setStatus('succeeded');

            // Créer une facture pour ce paiement si nécessaire
            if ($this->invoiceService) {
                try {
                    $stripeInvoiceId = $eventData['invoice'] ?? null;
                    $this->invoiceService->createInvoiceForPayment($payment, $stripeInvoiceId);
                    $this->logger->info('Facture créée pour le paiement', [
                        'payment_id' => $payment->getId()->toRfc4122(),
                        'stripe_invoice_id' => $stripeInvoiceId,
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de la création de la facture pour le paiement', [
                        'payment_id' => $payment->getId()->toRfc4122(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            $payment->setStatus('pending');
        }

        $this->paymentRepository->save($payment);
        return $payment;
    }

    /**
     * Gère un événement de réussite de payment intent
     * @param array<string, mixed> $eventData
     */
    private function handlePaymentIntentSucceeded(array $eventData): ?Payment
    {
        $paymentIntentId = $eventData['id'] ?? null;

        if (!$paymentIntentId) {
            $this->logger->warning('Webhook payment_intent.succeeded sans payment intent ID');
            return null;
        }

        // Rechercher le paiement associé à ce payment intent
        $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            $this->logger->warning('Aucun paiement trouvé pour le payment intent', [
                'payment_intent_id' => $paymentIntentId
            ]);
            return null;
        }

        // Mettre à jour le statut
        $payment->setStatus('succeeded');
        $this->paymentRepository->save($payment);

        $this->logger->info('Paiement marqué comme réussi', [
            'payment_id' => $payment->getId()->toRfc4122(),
            'payment_intent_id' => $paymentIntentId,
        ]);

        // Créer une facture pour ce paiement si nécessaire
        if ($this->invoiceService) {
            try {
                $stripeInvoiceId = $eventData['invoice'] ?? null;
                $this->invoiceService->createInvoiceForPayment($payment, $stripeInvoiceId);
                $this->logger->info('Facture créée pour le paiement', [
                    'payment_id' => $payment->getId()->toRfc4122(),
                    'stripe_invoice_id' => $stripeInvoiceId,
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la création de la facture pour le paiement', [
                    'payment_id' => $payment->getId()->toRfc4122(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment;
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
