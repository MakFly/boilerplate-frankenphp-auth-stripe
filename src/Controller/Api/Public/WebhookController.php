<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Service\Invoice\InvoiceService;
use App\Service\Payment\PaymentServiceFactory;
use Psr\Log\LoggerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentServiceFactory $paymentServiceFactory,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $stripeWebhookSecret,
        private readonly LoggerInterface $logger,
        private readonly ?InvoiceService $invoiceService = null,
    ) {
    }
    
    #[Route('/api/webhook/stripe', name: 'api_webhook_stripe', methods: ['POST'])]
    public function handleStripeWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        
        if (!$sigHeader) {
            $this->logger->warning('Webhook reçu sans signature Stripe');
            return new Response('Signature manquante', Response::HTTP_BAD_REQUEST);
        }

        try {
            // Vérification de la signature pour s'assurer que la requête vient bien de Stripe
            $event = Webhook::constructEvent(
                $payload, $sigHeader, $this->stripeWebhookSecret
            );
        } catch (SignatureVerificationException $e) {
            $this->logger->warning('Signature webhook invalide: ' . $e->getMessage());
            return new Response('Signature invalide', Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement du webhook: ' . $e->getMessage());
            return new Response('Erreur webhook', Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->processWebhookEvent($event);
            return new Response('Webhook reçu avec succès', Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement de l\'événement webhook: ' . $e->getMessage());
            return new Response('Erreur lors du traitement', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function processWebhookEvent(Event $event): void
    {
        $eventType = $event->type;
        $eventData = $event->data->object->toArray();

        $this->logger->info('Traitement webhook Stripe', [
            'event_type' => $eventType,
            'event_id' => $event->id,
        ]);
        
        // Traitement des événements de facture si le service est disponible
        if ($this->invoiceService !== null && str_starts_with($eventType, 'invoice.')) {
            $success = $this->invoiceService->handleInvoiceWebhook($eventType, $eventData);
            if ($success) {
                return;
            }
        }
        
        // Déterminer le type de service à utiliser en fonction de l'événement
        $serviceType = 'payment_intent';
        
        // Les événements liés aux abonnements
        if (strpos($eventType, 'customer.subscription') === 0) {
            $serviceType = 'subscription';
        }
        
        $paymentService = $this->paymentServiceFactory->create($serviceType);
        $success = $paymentService->handleWebhook($eventType, $eventData);
        
        if (!$success) {
            $this->logger->warning('L\'événement webhook n\'a pas été traité avec succès', [
                'event_type' => $eventType,
                'event_id' => $event->id,
            ]);
        }
    }
}