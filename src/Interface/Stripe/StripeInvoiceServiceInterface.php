<?php

declare(strict_types=1);

namespace App\Interface\Stripe;

use App\Entity\Payment;
use App\Entity\Subscription;
use Stripe\Invoice as StripeInvoice;

interface StripeInvoiceServiceInterface
{
    /**
     * Create a Stripe invoice for a payment
     */
    public function createStripeInvoiceForPayment(Payment $payment): StripeInvoice;

    /**
     * Create a Stripe invoice for a subscription
     */
    public function createStripeInvoiceForSubscription(Subscription $subscription): StripeInvoice;

    /**
     * Retrieve a Stripe invoice by ID
     */
    public function retrieveStripeInvoice(string $stripeInvoiceId): StripeInvoice;

    /**
     * Mark a Stripe invoice as paid
     */
    public function markStripeInvoiceAsPaid(string $stripeInvoiceId): StripeInvoice;
}