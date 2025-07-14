<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Interface\InvoiceServiceInterface;
use App\Interface\Stripe\StripeInvoiceServiceInterface;
use App\Repository\InvoiceRepository;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use Stripe\Exception\ApiErrorException;

readonly class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private PaymentRepository $paymentRepository,
        private SubscriptionRepository $subscriptionRepository,
        private StripeInvoiceServiceInterface $stripeInvoiceService
    ) {
    }

    /**
     * Creates an invoice for a one-time payment
     * 
     * @param string|null $stripeInvoiceId Stripe invoice ID if already created (via webhook)
     */
    public function createInvoiceForPayment(Payment $payment, ?string $stripeInvoiceId = null): Invoice
    {
        // Check if an invoice already exists for this payment
        $existingInvoice = $this->invoiceRepository->findByPayment($payment);
        if ($existingInvoice !== null) {
            return $existingInvoice;
        }
        
        // Check if an invoice already exists with this Stripe ID
        if ($stripeInvoiceId) {
            $existingInvoiceByStripeId = $this->invoiceRepository->findOneByStripeInvoiceId($stripeInvoiceId);
            if ($existingInvoiceByStripeId !== null) {
                // If the invoice exists but is not linked to the payment, link it
                if ($existingInvoiceByStripeId->getPayment() === null) {
                    $existingInvoiceByStripeId->setPayment($payment);
                    $this->invoiceRepository->save($existingInvoiceByStripeId);
                }
                return $existingInvoiceByStripeId;
            }
        }

        $user = $payment->getUser();

        try {
            // If a Stripe invoice ID is provided, retrieve this invoice
            if ($stripeInvoiceId) {
                $stripeInvoice = $this->stripeInvoiceService->retrieveStripeInvoice($stripeInvoiceId);
            } else {
                // Otherwise, create a new invoice via the Stripe service
                $stripeInvoice = $this->stripeInvoiceService->createStripeInvoiceForPayment($payment);
            }

            // Create the local Invoice entity
            $invoice = new Invoice();
            $invoice->setStripeInvoiceId($stripeInvoice->id)
                ->setUser($user)
                ->setAmount($stripeInvoice->amount_paid ?: $payment->getAmount())
                ->setCurrency($stripeInvoice->currency ?: $payment->getCurrency())
                ->setDescription($payment->getDescription())
                ->setPayment($payment)
                ->setStatus($payment->getStatus() === 'succeeded' || $stripeInvoice->status === 'paid' ? Invoice::STATUS_PAID : Invoice::STATUS_OPEN);

            if (isset($stripeInvoice->invoice_pdf)) {
                $invoice->setPdfUrl($stripeInvoice->invoice_pdf);
            }

            $this->invoiceRepository->save($invoice);

            return $invoice;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error creating invoice: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Creates an invoice for a subscription
     * 
     * @param string|null $stripeInvoiceId Stripe invoice ID if already created (via webhook)
     */
    public function createInvoiceForSubscription(Subscription $subscription, ?string $stripeInvoiceId = null): Invoice
    {
        // On ne crée jamais de facture Stripe pour un abonnement ici.
        // On attend que Stripe la crée et on la synchronise via le webhook.
        $existingInvoice = $this->invoiceRepository->findBySubscription($subscription);
        if ($existingInvoice !== null) {
            return $existingInvoice;
        }
        
        // Check if an invoice already exists with this Stripe ID
        if ($stripeInvoiceId) {
            $existingInvoiceByStripeId = $this->invoiceRepository->findOneByStripeInvoiceId($stripeInvoiceId);
            if ($existingInvoiceByStripeId !== null) {
                // If the invoice exists but is not linked to the subscription, link it
                if ($existingInvoiceByStripeId->getSubscription() === null) {
                    $existingInvoiceByStripeId->setSubscription($subscription);
                    $this->invoiceRepository->save($existingInvoiceByStripeId);
                }
                return $existingInvoiceByStripeId;
            }
        }

        $user = $subscription->getUser();

        try {
            // Si pas de stripeInvoiceId, c'est une erreur métier : la facture doit être créée par Stripe et reçue via webhook
            if ($stripeInvoiceId) {
                $stripeInvoice = $this->stripeInvoiceService->retrieveStripeInvoice($stripeInvoiceId);
            } else {
                throw new \RuntimeException('La facture Stripe pour un abonnement doit être créée par Stripe et transmise via le webhook.');
            }

            // Create the local Invoice entity
            $invoice = new Invoice();
            $invoice->setStripeInvoiceId($stripeInvoice->id)
                ->setUser($user)
                ->setAmount($stripeInvoice->amount_paid ?: $subscription->getAmount())
                ->setCurrency($stripeInvoice->currency ?: $subscription->getCurrency())
                ->setDescription(sprintf('Subscription %s', $subscription->getInterval() === 'annual' ? 'annual' : 'monthly'))
                ->setSubscription($subscription)
                ->setStatus($stripeInvoice->status === 'paid' ? Invoice::STATUS_PAID : Invoice::STATUS_OPEN);

            if (isset($stripeInvoice->invoice_pdf)) {
                $invoice->setPdfUrl($stripeInvoice->invoice_pdf);
            }

            $this->invoiceRepository->save($invoice);

            return $invoice;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error creating invoice: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Updates invoice status based on associated payment
     */
    public function updateInvoiceFromPayment(Payment $payment): ?Invoice
    {
        $invoice = $this->invoiceRepository->findByPayment($payment);
        
        if (!$invoice) {
            return null;
        }

        $newStatus = match ($payment->getStatus()) {
            'succeeded' => Invoice::STATUS_PAID,
            'failed' => Invoice::STATUS_VOID,
            default => $invoice->getStatus(),
        };

        if ($invoice->getStatus() !== $newStatus) {
            $invoice->setStatus($newStatus);
            $this->invoiceRepository->save($invoice);
            
            // If the invoice is now paid and we have a Stripe invoice ID
            if ($newStatus === Invoice::STATUS_PAID && $invoice->getStripeInvoiceId()) {
                $this->syncWithStripeInvoice($invoice);
            }
        }

        return $invoice;
    }

    /**
     * Updates invoice status based on associated subscription
     */
    public function updateInvoiceFromSubscription(Subscription $subscription): ?Invoice
    {
        $invoice = $this->invoiceRepository->findBySubscription($subscription);
        
        if (!$invoice) {
            return null;
        }

        $newStatus = match ($subscription->getStatus()) {
            'active' => Invoice::STATUS_PAID,
            'canceled' => Invoice::STATUS_VOID,
            'past_due', 'unpaid' => Invoice::STATUS_PAST_DUE,
            default => $invoice->getStatus(),
        };

        if ($invoice->getStatus() !== $newStatus) {
            $invoice->setStatus($newStatus);
            $this->invoiceRepository->save($invoice);
            
            // If the invoice is now paid and we have a Stripe invoice ID
            if ($newStatus === Invoice::STATUS_PAID && $invoice->getStripeInvoiceId()) {
                $this->syncWithStripeInvoice($invoice);
            }
        }

        return $invoice;
    }

    /**
     * Processes invoice-related webhooks
     * @param array<string, mixed> $eventData
     */
    public function handleInvoiceWebhook(string $eventType, array $eventData): bool
    {
        $invoiceId = $eventData['id'] ?? null;
        $customerId = $eventData['customer'] ?? null;
        $subscriptionId = $eventData['subscription'] ?? null;
        $paymentIntentId = $eventData['payment_intent'] ?? null;

        if (!$invoiceId) {
            return false;
        }

        // Check if this invoice already exists in our system
        $invoice = $this->invoiceRepository->findOneByStripeInvoiceId($invoiceId);

        // For invoice events related to a subscription
        if ($subscriptionId && is_string($subscriptionId)) {
            $subscription = $this->subscriptionRepository->findOneByStripeSubscriptionId($subscriptionId);
            
            if ($subscription) {
                // If the invoice already exists, update its status
                if ($invoice) {
                    $invoice->setStatus(match ($eventType) {
                        'invoice.payment_succeeded' => Invoice::STATUS_PAID,
                        'invoice.payment_failed' => Invoice::STATUS_PAST_DUE,
                        default => $invoice->getStatus()
                    });
                    $this->invoiceRepository->save($invoice);
                } else {
                    // Otherwise, create a new invoice for this subscription
                    try {
                        $invoice = $this->createInvoiceForSubscription($subscription, $invoiceId);
                    } catch (\Exception $e) {
                        return false;
                    }
                }
                return true;
            }
        }
        
        // For invoice events related to a payment
        if ($paymentIntentId && is_string($paymentIntentId)) {
            $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);
            
            if ($payment) {
                // If the invoice already exists, update its status
                if ($invoice) {
                    $invoice->setStatus(match ($eventType) {
                        'invoice.payment_succeeded' => Invoice::STATUS_PAID,
                        'invoice.payment_failed' => Invoice::STATUS_PAST_DUE,
                        default => $invoice->getStatus()
                    });
                    $this->invoiceRepository->save($invoice);
                } else {
                    // Otherwise, create a new invoice for this payment
                    try {
                        $invoice = $this->createInvoiceForPayment($payment, $invoiceId);
                    } catch (\Exception $e) {
                        return false;
                    }
                }
                return true;
            }
        }
        
        // If no payment or subscription found, create a pending virtual invoice
        // that will be linked later to a payment or subscription
        if (!$invoice && $customerId) {
            $invoice = new Invoice();
            $invoice->setStripeInvoiceId($invoiceId)
                ->setStatus(match ($eventType) {
                    'invoice.payment_succeeded' => Invoice::STATUS_PAID,
                    'invoice.payment_failed' => Invoice::STATUS_PAST_DUE,
                    default => Invoice::STATUS_OPEN
                });
                
            // Extract other invoice information
            if (isset($eventData['amount_paid'])) {
                $invoice->setAmount((int) $eventData['amount_paid']);
            }
            
            if (isset($eventData['currency'])) {
                $invoice->setCurrency($eventData['currency']);
            }
                
            $this->invoiceRepository->save($invoice);
            return true;
        }
        
        return false;
    }

    /**
     * Synchronizes a local invoice with Stripe
     */
    private function syncWithStripeInvoice(Invoice $invoice): void
    {
        try {
            $stripeInvoice = $this->stripeInvoiceService->retrieveStripeInvoice($invoice->getStripeInvoiceId());
            
            // If the invoice is not already marked as paid in Stripe
            if ($stripeInvoice->status !== 'paid') {
                $stripeInvoice = $this->stripeInvoiceService->markStripeInvoiceAsPaid($invoice->getStripeInvoiceId());
            }
            
            // Update the PDF URL if available
            if ($stripeInvoice->invoice_pdf && $invoice->getPdfUrl() !== $stripeInvoice->invoice_pdf) {
                $invoice->setPdfUrl($stripeInvoice->invoice_pdf);
                $this->invoiceRepository->save($invoice);
            }
        } catch (\Exception $e) {
            // Log the error but don't stop the process
        }
    }
}