<?php

declare(strict_types=1);

namespace App\Service\Webhook;

use App\Entity\StripeWebhookLog;
use App\Repository\StripeWebhookLogRepository;
use App\Service\Payment\PaymentServiceFactory;

final readonly class WebhookRetryService
{
    public function __construct(
        private StripeWebhookLogRepository $webhookLogRepository,
        private PaymentServiceFactory $paymentServiceFactory,
        private SubscriptionCreationService $subscriptionCreationService,
        private WebhookLoggerService $webhookLoggerService
    ) {
    }

    /**
     * Retries failed webhooks
     */
    public function retryFailedWebhooks(int $limit = 10): int
    {
        $failedWebhooks = $this->webhookLogRepository->findErrors($limit);
        $successCount = 0;
        
        foreach ($failedWebhooks as $webhookLog) {
            if ($this->retryWebhook($webhookLog)) {
                $successCount++;
            }
        }
        
        return $successCount;
    }

    /**
     * Retries a specific webhook
     */
    private function retryWebhook(StripeWebhookLog $webhookLog): bool
    {
        // Increment the retry counter
        $webhookLog->incrementRetryCount();
        
        try {
            // Get webhook data
            $eventData = $webhookLog->getPayload();
            $eventType = $webhookLog->getEventType();
            $processorType = $webhookLog->getProcessorType();
            
            // Special handling for subscription creation events
            if ($this->handleSubscriptionCreation($eventType, $eventData)) {
                $this->webhookLoggerService->markAsSuccess($webhookLog);
                return true;
            }
            
            // Retry the event with the appropriate service
            $paymentService = $this->paymentServiceFactory->create($processorType);
            $success = $paymentService->handleWebhook($eventType, $eventData);
            
            if ($success) {
                $webhookLog->markAsSuccess();
                return true;
            } else {
                $webhookLog->markAsIgnored('Event not processed during retry');
                return false;
            }
        } catch (\Exception $e) {
            // Update error message
            $webhookLog->markAsError($e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'retry_count' => $webhookLog->getRetryCount()
            ]);
            return false;
        } finally {
            // Update the log
            $this->webhookLogRepository->save($webhookLog);
        }
    }

    /**
     * Handles automatic creation of missed subscriptions
     */
    private function handleSubscriptionCreation(string $eventType, array $eventData): bool
    {
        if ($eventType === 'customer.subscription.created') {
            $subscription = $this->subscriptionCreationService->createFromWebhookData($eventData);
            return $subscription !== null;
        }
        
        return false;
    }
}