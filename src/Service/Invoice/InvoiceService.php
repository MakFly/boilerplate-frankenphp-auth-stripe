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

final readonly class InvoiceService
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
     */
    public function createInvoiceForPayment(Payment $payment): Invoice
    {
        // Vérifier si une facture existe déjà pour ce paiement
        $existingInvoice = $this->invoiceRepository->findByPayment($payment);
        if ($existingInvoice !== null) {
            return $existingInvoice;
        }

        // Récupérer le client Stripe ou en créer un
        $user = $payment->getUser();
        $customerId = $user->getStripeCustomerId();
        
        if ($customerId === null) {
            throw new \RuntimeException('L\'utilisateur doit avoir un customer ID Stripe pour créer une facture.');
        }

        try {
            // Créer un élément de facture dans Stripe
            $invoiceItem = $this->stripe->invoiceItems->create([
                'customer' => $customerId,
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
                'description' => $payment->getDescription() ?? 'Paiement ponctuel',
            ]);

            // Créer la facture dans Stripe
            $stripeInvoice = $this->stripe->invoices->create([
                'customer' => $customerId,
                'auto_advance' => true, // Finaliser automatiquement la facture
                'collection_method' => 'charge_automatically',
                'metadata' => [
                    'payment_id' => $payment->getId()->toRfc4122(),
                ],
            ]);

            // Finaliser la facture
            $stripeInvoice = $this->stripe->invoices->finalizeInvoice($stripeInvoice->id);
            
            // Pour les paiements ponctuels qui ont déjà été payés, marquer la facture comme payée
            if ($payment->getStatus() === 'succeeded') {
                $stripeInvoice = $this->stripe->invoices->pay($stripeInvoice->id, ['paid_out_of_band' => true]);
            }

            // Créer l'entité Invoice locale
            $invoice = new Invoice();
            $invoice->setStripeInvoiceId($stripeInvoice->id)
                ->setUser($user)
                ->setAmount($payment->getAmount())
                ->setCurrency($payment->getCurrency())
                ->setDescription($payment->getDescription())
                ->setPayment($payment)
                ->setStatus($payment->getStatus() === 'succeeded' ? Invoice::STATUS_PAID : Invoice::STATUS_OPEN);

            if ($stripeInvoice->invoice_pdf) {
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
     * Gère les webhooks de facture Stripe
     * 
     * @param array{id?: string} $eventData Les données de l'événement webhook
     */
    public function handleInvoiceWebhook(string $eventType, array $eventData): bool
    {
        $invoiceId = $eventData['id'] ?? null;
        
        if (!$invoiceId) {
            return false;
        }
        
        $invoice = $this->invoiceRepository->findByStripeInvoiceId($invoiceId);
        
        // Si on n'a pas d'invoice locale correspondante, on ignore
        if (!$invoice) {
            return false;
        }
        
        switch ($eventType) {
            case 'invoice.payment_succeeded':
                $invoice->setStatus(Invoice::STATUS_PAID);
                $this->invoiceRepository->save($invoice);
                return true;
                
            case 'invoice.payment_failed':
                $invoice->setStatus(Invoice::STATUS_UNCOLLECTIBLE);
                $this->invoiceRepository->save($invoice);
                return true;
                
            case 'invoice.voided':
                $invoice->setStatus(Invoice::STATUS_VOID);
                $this->invoiceRepository->save($invoice);
                return true;
                
            default:
                return false;
        }
    }
}