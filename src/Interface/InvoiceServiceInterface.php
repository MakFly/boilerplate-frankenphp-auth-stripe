<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Subscription;

interface InvoiceServiceInterface
{
    /**
     * Create invoice for payment
     *
     * @param Payment $payment
     * @param string|null $stripeInvoiceId
     * @return Invoice
     */
    public function createInvoiceForPayment(Payment $payment, ?string $stripeInvoiceId = null): Invoice;

    /**
     * Create invoice for subscription
     *
     * @param Subscription $subscription
     * @param string|null $stripeInvoiceId
     * @return Invoice
     */
    public function createInvoiceForSubscription(Subscription $subscription, ?string $stripeInvoiceId = null): Invoice;

    /**
     * Update invoice from payment
     *
     * @param Payment $payment
     * @return Invoice|null
     */
    public function updateInvoiceFromPayment(Payment $payment): ?Invoice;

    /**
     * Update invoice from subscription
     *
     * @param Subscription $subscription
     * @return Invoice|null
     */
    public function updateInvoiceFromSubscription(Subscription $subscription): ?Invoice;

    /**
     * Handle invoice webhook events
     *
     * @param string $eventType
     * @param array $eventData
     * @return bool
     */
    public function handleInvoiceWebhook(string $eventType, array $eventData): bool;
}