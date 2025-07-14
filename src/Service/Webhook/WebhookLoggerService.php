<?php

declare(strict_types=1);

namespace App\Service\Webhook;

use App\Entity\StripeWebhookLog;
use App\Repository\StripeWebhookLogRepository;
use App\Service\Payment\PaymentServiceFactory;
use Psr\Log\LoggerInterface;
use Stripe\Event;

final readonly class WebhookLoggerService
{
    public function __construct(
        private StripeWebhookLogRepository $webhookLogRepository,
        private PaymentServiceFactory $paymentServiceFactory,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Creates a log for a webhook event
     */
    public function createLog(Event $event, array $eventData): StripeWebhookLog
    {
        $this->logger->info('Processing a Stripe webhook event', [
            'event_id' => $event->id,
            'event_type' => $event->type,
        ]);
        
        $log = new StripeWebhookLog();
        $log->setEventId($event->id)
            ->setEventType($event->type)
            ->setPayload($eventData)
            ->setProcessorType($this->paymentServiceFactory->determineProcessorTypeFromEvent($event->type, $eventData))
            ->setStatus(StripeWebhookLog::STATUS_PROCESSING);
        
        $this->webhookLogRepository->save($log);
        
        return $log;
    }

    /**
     * Marks a log as successful
     */
    public function markAsSuccess(StripeWebhookLog $log): void
    {
        $log->setStatus(StripeWebhookLog::STATUS_SUCCESS);
        $this->webhookLogRepository->save($log);
        
        $this->logger->info('Webhook event processed successfully', [
            'event_id' => $log->getEventId(),
            'event_type' => $log->getEventType(),
        ]);
    }

    /**
     * Marks a log as ignored
     */
    public function markAsIgnored(StripeWebhookLog $log): void
    {
        $log->setStatus(StripeWebhookLog::STATUS_IGNORED);
        $this->webhookLogRepository->save($log);
        
        $this->logger->info('Webhook event ignored', [
            'event_id' => $log->getEventId(),
            'event_type' => $log->getEventType(),
        ]);
    }

    /**
     * Marks a log as error
     */
    public function markAsError(StripeWebhookLog $log, \Exception $exception): void
    {
        $log->setStatus(StripeWebhookLog::STATUS_ERROR)
            ->setErrorMessage($exception->getMessage());
        
        $this->webhookLogRepository->save($log);
        
        $this->logger->error('Error processing webhook event', [
            'event_id' => $log->getEventId(),
            'event_type' => $log->getEventType(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}