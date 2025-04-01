<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Repository\InvoiceRepository;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice as StripeInvoice;
use Stripe\InvoiceItem;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class InvoiceService
{
    private StripeClient $stripe;

    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private string $stripeSecretKey,
        private InvoiceRepository $invoiceRepository,
        private PaymentRepository $paymentRepository,
        private SubscriptionRepository $subscriptionRepository,
        ?StripeClient $stripeClient = null
    ) {
        $this->stripe = $stripeClient ?? new StripeClient($this->stripeSecretKey);
    }

    /**
     * Crée une facture pour un paiement ponctuel
     * 
     * @param string|null $stripeInvoiceId ID de la facture Stripe si déjà créée (via webhook)
     */
    public function createInvoiceForPayment(Payment $payment, ?string $stripeInvoiceId = null): Invoice
    {
        // Vérifier si une facture existe déjà pour ce paiement
        $existingInvoice = $this->invoiceRepository->findByPayment($payment);
        if ($existingInvoice !== null) {
            return $existingInvoice;
        }
        
        // Vérifier si une facture existe déjà avec cet ID Stripe
        if ($stripeInvoiceId) {
            $existingInvoiceByStripeId = $this->invoiceRepository->findOneByStripeInvoiceId($stripeInvoiceId);
            if ($existingInvoiceByStripeId !== null) {
                // Si la facture existe mais n'est pas liée au paiement, la lier
                if ($existingInvoiceByStripeId->getPayment() === null) {
                    $existingInvoiceByStripeId->setPayment($payment);
                    $this->invoiceRepository->save($existingInvoiceByStripeId);
                }
                return $existingInvoiceByStripeId;
            }
        }

        // Récupérer le client Stripe ou en créer un
        $user = $payment->getUser();
        $customerId = $user->getStripeCustomerId();
        
        if ($customerId === null) {
            throw new \RuntimeException('L\'utilisateur doit avoir un customer ID Stripe pour créer une facture.');
        }

        try {
            // Vérifier que le montant est bien défini et non nul
            if ($payment->getAmount() <= 0) {
                throw new \RuntimeException('Le montant du paiement doit être supérieur à zéro pour créer une facture.');
            }
            
            // Si un ID de facture Stripe est fourni, récupérer cette facture
            if ($stripeInvoiceId) {
                $stripeInvoice = $this->stripe->invoices->retrieve($stripeInvoiceId);
            } else {
                // Sinon, créer une nouvelle facture
                // Créer un élément de facture dans Stripe
                $invoiceItem = $this->stripe->invoiceItems->create([
                    'customer' => $customerId,
                    'amount' => $payment->getAmount(),
                    'currency' => $payment->getCurrency(),
                    'description' => $payment->getDescription() ?? 'Paiement ponctuel',
                ]);

                // Créer la facture dans Stripe en associant explicitement l'élément de facture
                $stripeInvoice = $this->stripe->invoices->create([
                    'customer' => $customerId,
                    'auto_advance' => true, // Finaliser automatiquement la facture
                    'collection_method' => 'charge_automatically',
                    'pending_invoice_items_behavior' => 'include', // Important: inclure tous les éléments de facture en attente
                    'metadata' => [
                        'payment_id' => $payment->getId()->toRfc4122(),
                        'description' => $payment->getDescription(),
                        'amount' => $payment->getAmount(),
                        'currency' => $payment->getCurrency(),
                        'payment_method' => $payment->getPaymentType(),
                    ],
                ]);

                // Vérifier que le montant de la facture correspond au montant du paiement
                if ((int)$stripeInvoice->amount_due !== (int)$payment->getAmount()) {
                    throw new \RuntimeException(sprintf(
                        'Écart détecté entre le montant du paiement (%d) et le montant de la facture (%d)',
                        $payment->getAmount(),
                        $stripeInvoice->amount_due
                    ));
                }

                // Finaliser la facture
                $stripeInvoice = $this->stripe->invoices->finalizeInvoice($stripeInvoice->id);
                
                // Pour les paiements ponctuels qui ont déjà été payés, marquer la facture comme payée
                if ($payment->getStatus() === 'succeeded' && $stripeInvoice->status !== 'paid') {
                    $stripeInvoice = $this->stripe->invoices->pay($stripeInvoice->id, ['paid_out_of_band' => true]);
                }
            }

            // Créer l'entité Invoice locale
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
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Erreur lors de la création de la facture Stripe: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Crée une facture pour un abonnement
     * 
     * @param string|null $stripeInvoiceId ID de la facture Stripe si déjà créée (via webhook)
     */
    public function createInvoiceForSubscription(Subscription $subscription, ?string $stripeInvoiceId = null): Invoice
    {
        // Vérifier si une facture existe déjà pour cet abonnement
        $existingInvoice = $this->invoiceRepository->findBySubscription($subscription);
        if ($existingInvoice !== null) {
            return $existingInvoice;
        }
        
        // Vérifier si une facture existe déjà avec cet ID Stripe
        if ($stripeInvoiceId) {
            $existingInvoiceByStripeId = $this->invoiceRepository->findOneByStripeInvoiceId($stripeInvoiceId);
            if ($existingInvoiceByStripeId !== null) {
                // Si la facture existe mais n'est pas liée à l'abonnement, la lier
                if ($existingInvoiceByStripeId->getSubscription() === null) {
                    $existingInvoiceByStripeId->setSubscription($subscription);
                    $this->invoiceRepository->save($existingInvoiceByStripeId);
                }
                return $existingInvoiceByStripeId;
            }
        }

        $user = $subscription->getUser();
        $customerId = $user->getStripeCustomerId();
        
        if ($customerId === null) {
            throw new \RuntimeException('L\'utilisateur doit avoir un customer ID Stripe pour créer une facture.');
        }

        try {
            // Si un ID de facture Stripe est fourni, récupérer cette facture
            if ($stripeInvoiceId) {
                $stripeInvoice = $this->stripe->invoices->retrieve($stripeInvoiceId);
            } else {
                // Sinon, créer une nouvelle facture
                // Créer un élément de facture dans Stripe
                $invoiceItem = $this->stripe->invoiceItems->create([
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
            }

            // Créer l'entité Invoice locale
            $invoice = new Invoice();
            $invoice->setStripeInvoiceId($stripeInvoice->id)
                ->setUser($user)
                ->setAmount($stripeInvoice->amount_paid ?: $subscription->getAmount())
                ->setCurrency($stripeInvoice->currency ?: $subscription->getCurrency())
                ->setDescription(sprintf('Abonnement %s', $subscription->getInterval() === 'annual' ? 'annuel' : 'mensuel'))
                ->setSubscription($subscription)
                ->setStatus($stripeInvoice->status === 'paid' ? Invoice::STATUS_PAID : Invoice::STATUS_OPEN);

            if (isset($stripeInvoice->invoice_pdf)) {
                $invoice->setPdfUrl($stripeInvoice->invoice_pdf);
            }

            $this->invoiceRepository->save($invoice);

            return $invoice;
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Erreur lors de la création de la facture Stripe: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Met à jour le statut d'une facture en fonction du paiement associé
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
            
            // Si la facture est maintenant payée et que l'on a un ID de facture Stripe
            if ($newStatus === Invoice::STATUS_PAID && $invoice->getStripeInvoiceId()) {
                try {
                    $stripeInvoice = $this->stripe->invoices->retrieve($invoice->getStripeInvoiceId());
                    
                    // Si la facture n'est pas déjà marquée comme payée dans Stripe
                    if ($stripeInvoice->status !== 'paid') {
                        $stripeInvoice = $this->stripe->invoices->pay($stripeInvoice->id, ['paid_out_of_band' => true]);
                    }
                    
                    // Mettre à jour l'URL du PDF s'il est disponible
                    if ($stripeInvoice->invoice_pdf && $invoice->getPdfUrl() !== $stripeInvoice->invoice_pdf) {
                        $invoice->setPdfUrl($stripeInvoice->invoice_pdf);
                        $this->invoiceRepository->save($invoice);
                    }
                } catch (ApiErrorException $e) {
                    // Log l'erreur mais ne pas arrêter le processus
                }
            }
        }

        return $invoice;
    }

    /**
     * Met à jour le statut d'une facture en fonction de l'abonnement associé
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
            
            // Si la facture est maintenant payée et que l'on a un ID de facture Stripe
            if ($newStatus === Invoice::STATUS_PAID && $invoice->getStripeInvoiceId()) {
                try {
                    $stripeInvoice = $this->stripe->invoices->retrieve($invoice->getStripeInvoiceId());
                    
                    // Si la facture n'est pas déjà marquée comme payée dans Stripe
                    if ($stripeInvoice->status !== 'paid') {
                        $stripeInvoice = $this->stripe->invoices->pay($stripeInvoice->id, ['paid_out_of_band' => true]);
                    }
                    
                    // Mettre à jour l'URL du PDF s'il est disponible
                    if ($stripeInvoice->invoice_pdf && $invoice->getPdfUrl() !== $stripeInvoice->invoice_pdf) {
                        $invoice->setPdfUrl($stripeInvoice->invoice_pdf);
                        $this->invoiceRepository->save($invoice);
                    }
                } catch (ApiErrorException $e) {
                    // Log l'erreur mais ne pas arrêter le processus
                }
            }
        }

        return $invoice;
    }

    /**
     * Traite les webhooks liés aux factures
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

        // Vérifier si cette facture existe déjà dans notre système
        $invoice = $this->invoiceRepository->findOneByStripeInvoiceId($invoiceId);

        // Pour les événements de facture liés à un abonnement
        if ($subscriptionId && is_string($subscriptionId)) {
            $subscription = $this->subscriptionRepository->findOneByStripeSubscriptionId($subscriptionId);
            
            if ($subscription) {
                // Si la facture existe déjà, mettre à jour son statut
                if ($invoice) {
                    $invoice->setStatus(match ($eventType) {
                        'invoice.payment_succeeded' => Invoice::STATUS_PAID,
                        'invoice.payment_failed' => Invoice::STATUS_PAST_DUE,
                        default => $invoice->getStatus()
                    });
                    $this->invoiceRepository->save($invoice);
                } else {
                    // Sinon, créer une nouvelle facture pour cet abonnement
                    try {
                        $invoice = $this->createInvoiceForSubscription($subscription, $invoiceId);
                    } catch (\Exception $e) {
                        return false;
                    }
                }
                return true;
            }
        }
        
        // Pour les événements de facture liés à un paiement
        if ($paymentIntentId && is_string($paymentIntentId)) {
            $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);
            
            if ($payment) {
                // Si la facture existe déjà, mettre à jour son statut
                if ($invoice) {
                    $invoice->setStatus(match ($eventType) {
                        'invoice.payment_succeeded' => Invoice::STATUS_PAID,
                        'invoice.payment_failed' => Invoice::STATUS_PAST_DUE,
                        default => $invoice->getStatus()
                    });
                    $this->invoiceRepository->save($invoice);
                } else {
                    // Sinon, créer une nouvelle facture pour ce paiement
                    try {
                        $invoice = $this->createInvoiceForPayment($payment, $invoiceId);
                    } catch (\Exception $e) {
                        return false;
                    }
                }
                return true;
            }
        }
        
        // Si aucun paiement ou abonnement trouvé, créer une facture virtuelle en attente
        // qui sera liée ultérieurement à un paiement ou abonnement
        if (!$invoice && $customerId) {
            $invoice = new Invoice();
            $invoice->setStripeInvoiceId($invoiceId)
                ->setStatus(match ($eventType) {
                    'invoice.payment_succeeded' => Invoice::STATUS_PAID,
                    'invoice.payment_failed' => Invoice::STATUS_PAST_DUE,
                    default => Invoice::STATUS_OPEN
                });
                
            // Extraire d'autres informations de la facture
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
}