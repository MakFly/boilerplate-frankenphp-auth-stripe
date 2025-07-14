<?php

declare(strict_types=1);

namespace App\Service\Webhook;

use App\Entity\StripeWebhookLog;
use App\Repository\StripeWebhookLogRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class WebhookStatusService
{
    public function __construct(
        private StripeWebhookLogRepository $webhookLogRepository,
        #[Autowire('%env(STRIPE_CANCEL_URL)%')]
        private string $stripeCancelUrl
    ) {
    }

    /**
     * Checks webhook status by session ID
     */
    public function checkWebhookStatusBySessionId(string $sessionId): array
    {
        $webhook = $this->findWebhookBySessionId($sessionId);
        
        if (!$webhook) {
            return [
                'status' => 'pending',
                'message' => 'Payment in progress'
            ];
        }
        
        return $this->formatWebhookStatus($webhook);
    }

    /**
     * Finds a webhook by session ID
     */
    private function findWebhookBySessionId(string $sessionId): ?StripeWebhookLog
    {
        return $this->webhookLogRepository
            ->createQueryBuilder('w')
            ->where('w.payload LIKE :sessionId')
            ->setParameter('sessionId', '%"id":"' . $sessionId . '"%')
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Formats webhook status for response
     */
    private function formatWebhookStatus(StripeWebhookLog $webhook): array
    {
        return match ($webhook->getStatus()) {
            StripeWebhookLog::STATUS_SUCCESS => [
                'status' => 'success',
                'message' => 'Payment processed successfully'
            ],
            StripeWebhookLog::STATUS_ERROR => [
                'status' => 'error',
                'message' => 'An error occurred while processing payment',
                'error_details' => $webhook->getErrorMessage(),
                'redirect_url' => $this->stripeCancelUrl
            ],
            default => [
                'status' => $webhook->getStatus(),
                'message' => 'Payment in progress'
            ]
        };
    }

    /**
     * Determines if a webhook requires redirection to cancellation URL
     */
    public function shouldRedirectToCancel(StripeWebhookLog $webhookLog): bool
    {
        return $webhookLog->getStatus() === StripeWebhookLog::STATUS_ERROR;
    }

    /**
     * Gets redirect URL for failed checkout event
     */
    public function getErrorRedirectResponse(string $eventType): array
    {
        return [
            'status' => 'error',
            'message' => 'Une erreur est survenue lors du traitement du paiement',
            'redirect_url' => $this->stripeCancelUrl
        ];
    }
}