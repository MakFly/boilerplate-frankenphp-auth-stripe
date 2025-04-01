<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Interface\PaymentServiceInterface;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\Invoice\InvoiceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class PaymentServiceFactory
{
    public const TYPE_PAYMENT_INTENT = 'payment_intent';
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_DEFAULT = self::TYPE_PAYMENT_INTENT;
    
    /**
     * Event type to processor type mapping
     */
    private const EVENT_TYPE_MAPPING = [
        // Subscription events
        'customer.subscription.' => self::TYPE_SUBSCRIPTION,
        'invoice.payment_succeeded' => self::TYPE_SUBSCRIPTION,
        'invoice.payment_failed' => self::TYPE_SUBSCRIPTION,
        
        // Payment intent events
        'payment_intent.' => self::TYPE_PAYMENT_INTENT,
        'charge.' => self::TYPE_PAYMENT_INTENT,
        'checkout.session.' => self::TYPE_PAYMENT_INTENT, // Will be refined based on mode
    ];

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly PaymentRepository $paymentRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
        private readonly ?InvoiceService $invoiceService = null,
    ) {
    }

    /**
     * Create a payment service based on the specified type
     */
    public function create(string $type): PaymentServiceInterface
    {
        $this->logger->debug('Creating payment service', ['type' => $type]);
        
        // S'assurer que l'InvoiceService est disponible pour tous les services de paiement
        if ($this->invoiceService === null) {
            $this->logger->warning('InvoiceService non disponible. La création de factures sera désactivée.');
        }
        
        $baseService = match($type) {
            self::TYPE_PAYMENT_INTENT => new PaymentIntentService(
                $this->params->get('stripe_secret_key'),
                $this->params->get('stripe_success_url'),
                $this->params->get('stripe_cancel_url'),
                $this->paymentRepository,
                $this->invoiceService,
                null,
                $this->logger
            ),
            self::TYPE_SUBSCRIPTION => new SubscriptionService(
                $this->params->get('stripe_secret_key'),
                $this->params->get('stripe_success_url'),
                $this->params->get('stripe_cancel_url'),
                $this->subscriptionRepository,
                $this->invoiceService,
                null,
                $this->logger,
                $this->userRepository
            ),
            default => throw new \InvalidArgumentException("Type de service de paiement non reconnu: $type"),
        };

        // Application du décorateur à tous les services de paiement
        return new PaymentLoggerDecorator(
            $baseService,
            $this->paymentRepository,
            $this->subscriptionRepository,
            $this->userRepository,
            $this->logger
        );
    }
    
    /**
     * Determines the processor type based on the event type and data
     * @param array<string, mixed> $eventData
     */
    public function determineProcessorTypeFromEvent(string $eventType, array $eventData = []): string
    {
        // Special case for checkout.session.completed, check the mode
        if ($eventType === 'checkout.session.completed' && isset($eventData['mode'])) {
            if ($eventData['mode'] === 'subscription') {
                return self::TYPE_SUBSCRIPTION;
            }
            return self::TYPE_PAYMENT_INTENT;
        }
        
        // Check for subscription-related invoice events
        if (($eventType === 'invoice.payment_succeeded' || $eventType === 'invoice.payment_failed') 
            && isset($eventData['subscription'])) {
            return self::TYPE_SUBSCRIPTION;
        }
        
        // Use the mapping table for other events
        foreach (self::EVENT_TYPE_MAPPING as $prefix => $type) {
            if (str_starts_with($eventType, $prefix)) {
                return $type;
            }
        }
        
        // Default handler
        return self::TYPE_DEFAULT;
    }
}