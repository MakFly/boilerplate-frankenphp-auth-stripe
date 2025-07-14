<?php

declare(strict_types=1);

namespace App\Service\Webhook;

use App\Entity\StripeWebhookLog;
use App\Interface\WebhookProcessorInterface;
use App\Service\Payment\PaymentServiceFactory;
use App\Service\Webhook\WebhookLoggerService;
use App\Service\Webhook\WebhookRetryService;
use Stripe\Event;

final readonly class WebhookProcessor implements WebhookProcessorInterface
{
    public function __construct(
        private PaymentServiceFactory $paymentServiceFactory,
        private WebhookLoggerService $webhookLoggerService,
        private WebhookRetryService $webhookRetryService
    ) {
    }
    
    /**
     * Traite un événement webhook Stripe
     */
    public function processEvent(Event $event, array $eventData): StripeWebhookLog
    {
        // Créer le log pour cet événement
        $log = $this->webhookLoggerService->createLog($event, $eventData);
        
        try {
            // Obtenir le service de paiement approprié et traiter l'événement
            $processorType = $log->getProcessorType();
            $paymentService = $this->paymentServiceFactory->create($processorType);
            $success = $paymentService->handleWebhook($event->type, $eventData);
            
            if ($success) {
                $this->webhookLoggerService->markAsSuccess($log);
            } else {
                $this->webhookLoggerService->markAsIgnored($log);
            }
        } catch (\Exception $e) {
            $this->webhookLoggerService->markAsError($log, $e);
        }
        
        return $log;
    }
    
    
    /**
     * Retraite les webhooks en erreur
     */
    public function retryFailedWebhooks(int $limit = 10): int
    {
        return $this->webhookRetryService->retryFailedWebhooks($limit);
    }
} 