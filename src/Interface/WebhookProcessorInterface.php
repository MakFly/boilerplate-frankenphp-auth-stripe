<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\StripeWebhookLog;
use Stripe\Event;

interface WebhookProcessorInterface
{
    /**
     * Traite un événement webhook Stripe
     */
    public function processEvent(Event $event, array $eventData): StripeWebhookLog;

    /**
     * Retraite les webhooks en erreur
     */
    public function retryFailedWebhooks(int $limit = 10): int;
}