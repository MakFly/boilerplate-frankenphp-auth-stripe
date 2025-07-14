<?php

declare(strict_types=1);

namespace App\Service\Stripe;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Interface\Stripe\StripeInvoiceServiceInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice as StripeInvoice;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class StripeInvoiceService implements StripeInvoiceServiceInterface
{
    private StripeClient $stripe;

    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private string $stripeSecretKey,
        ?StripeClient $stripeClient = null
    ) {
        $this->stripe = $stripeClient ?? new StripeClient($this->stripeSecretKey);
    }

    /**
     * Create a Stripe invoice for a payment
     */
    public function createStripeInvoiceForPayment(Payment $payment): StripeInvoice
    {
        $customerId = $payment->getUser()->getStripeCustomerId();
        
        if ($customerId === null) {
            throw new \RuntimeException('User must have a Stripe customer ID to create an invoice.');
        }

        if ($payment->getAmount() <= 0) {
            throw new \RuntimeException('Le montant du paiement doit être supérieur à zéro pour créer une facture.');
        }

        try {
            // Créer un élément de facture dans Stripe
            $this->stripe->invoiceItems->create([
                'customer' => $customerId,
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
                'description' => $payment->getDescription() ?? 'Paiement ponctuel',
            ]);

            // Créer la facture dans Stripe
            $stripeInvoice = $this->stripe->invoices->create([
                'customer' => $customerId,
                'auto_advance' => true,
                'collection_method' => 'charge_automatically',
                'pending_invoice_items_behavior' => 'include',
                'metadata' => [
                    'payment_id' => $payment->getId()->toRfc4122(),
                    'description' => $payment->getDescription(),
                    'amount' => $payment->getAmount(),
                    'currency' => $payment->getCurrency(),
                    'payment_method' => $payment->getPaymentType(),
                ],
            ]);

            // Vérifier que le montant correspond
            if ((int)$stripeInvoice->amount_due !== (int)$payment->getAmount()) {
                throw new \RuntimeException(sprintf(
                    'Écart détecté entre le montant du paiement (%d) et le montant de la facture (%d)',
                    $payment->getAmount(),
                    $stripeInvoice->amount_due
                ));
            }

            // Finaliser la facture
            $stripeInvoice = $this->stripe->invoices->finalizeInvoice($stripeInvoice->id);
            
            // Marquer comme payée si le paiement est déjà réussi
            if ($payment->getStatus() === 'succeeded' && $stripeInvoice->status !== 'paid') {
                $stripeInvoice = $this->stripe->invoices->pay($stripeInvoice->id, ['paid_out_of_band' => true]);
            }

            return $stripeInvoice;
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Error creating Stripe invoice: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Créer une facture Stripe pour un abonnement
     */
    public function createStripeInvoiceForSubscription(Subscription $subscription): StripeInvoice
    {
        $customerId = $subscription->getUser()->getStripeCustomerId();
        
        if ($customerId === null) {
            throw new \RuntimeException('User must have a Stripe customer ID to create an invoice.');
        }

        try {
            // Créer un élément de facture dans Stripe
            $this->stripe->invoiceItems->create([
                'customer' => $customerId,
                'amount' => $subscription->getAmount(),
                'currency' => $subscription->getCurrency(),
                'description' => sprintf('Abonnement %s - %s', 
                    $subscription->getInterval() === 'year' ? 'annuel' : 'mensuel',
                    (new \DateTime())->format('d/m/Y')
                ),
            ]);

            // Créer la facture dans Stripe
            $stripeInvoice = $this->stripe->invoices->create([
                'customer' => $customerId,
                'auto_advance' => true,
                'collection_method' => 'charge_automatically',
                'pending_invoice_items_behavior' => 'include',
                'metadata' => [
                    'subscription_id' => $subscription->getId(),
                    'stripe_subscription_id' => $subscription->getStripeSubscriptionId(),
                    'interval' => $subscription->getInterval(),
                    'amount' => $subscription->getAmount(),
                    'currency' => $subscription->getCurrency(),
                ],
            ]);

            // Finaliser la facture
            $stripeInvoice = $this->stripe->invoices->finalizeInvoice($stripeInvoice->id);
            
            if ($subscription->getStatus() === 'active') {
                $stripeInvoice = $this->stripe->invoices->pay($stripeInvoice->id, ['paid_out_of_band' => true]);
            }

            return $stripeInvoice;
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Error creating Stripe invoice: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupérer une facture Stripe par ID
     */
    public function retrieveStripeInvoice(string $stripeInvoiceId): StripeInvoice
    {
        try {
            return $this->stripe->invoices->retrieve($stripeInvoiceId);
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Erreur lors de la récupération de la facture Stripe: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Marquer une facture Stripe comme payée
     */
    public function markStripeInvoiceAsPaid(string $stripeInvoiceId): StripeInvoice
    {
        try {
            return $this->stripe->invoices->pay($stripeInvoiceId, ['paid_out_of_band' => true]);
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Erreur lors du marquage de la facture comme payée: ' . $e->getMessage(), 0, $e);
        }
    }
}