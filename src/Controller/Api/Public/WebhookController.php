<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Entity\StripeWebhookLog;
use App\Service\Invoice\InvoiceService;
use App\Service\Payment\PaymentServiceFactory;
use App\Service\Webhook\WebhookProcessor;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $stripeWebhookSecret,
        #[Autowire('%env(STRIPE_CANCEL_URL)%')]
        private readonly string $stripeCancelUrl,
        private readonly PaymentServiceFactory $paymentServiceFactory,
        private readonly LoggerInterface $logger,
        private readonly WebhookProcessor $webhookProcessor,
        private readonly \Doctrine\ORM\EntityManagerInterface $entityManager,
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
            
            $this->logger->info('Webhook Stripe reçu', [
                'event_type' => $event->type,
                'event_id' => $event->id
            ]);
            
        } catch (SignatureVerificationException $e) {
            $this->logger->warning('Signature webhook invalide: ' . $e->getMessage());
            return new Response('Signature invalide', Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement du webhook: ' . $e->getMessage());
            return new Response('Erreur webhook', Response::HTTP_BAD_REQUEST);
        }

        try {
            // Conversion de l'objet Stripe en tableau pour faciliter le stockage
            $eventData = $event->data->object->toArray();
            
            // Enregistrement et traitement du webhook (synchrone ou asynchrone)
            $webhookLog = $this->webhookProcessor->processEvent($event, $eventData);
            
            if ($webhookLog->getStatus() === StripeWebhookLog::STATUS_ERROR) {
                $this->logger->error('Erreur lors du traitement du webhook', [
                    'event_id' => $event->id,
                    'webhook_log_id' => $webhookLog->getId()->toRfc4122(),
                    'error' => $webhookLog->getErrorMessage()
                ]);
                
                // Pour les webhooks checkout.session.completed en erreur, retourner une réponse spéciale
                // que le front-end peut intercepter
                if ($event->type === 'checkout.session.completed') {
                    return new JsonResponse([
                        'status' => 'error',
                        'message' => 'Une erreur est survenue lors du traitement du paiement',
                        'redirect_url' => $this->stripeCancelUrl
                    ], Response::HTTP_OK); // HTTP 200 car Stripe attend un succès
                }
            } else {
                $this->logger->info('Webhook traité avec succès', [
                    'event_id' => $event->id,
                    'webhook_log_id' => $webhookLog->getId()->toRfc4122()
                ]);
            }
            
            // Retourner toujours un succès à Stripe pour éviter les réessais
            return new Response('Webhook reçu', Response::HTTP_OK);
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement de l\'événement webhook: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Même en cas d'erreur, renvoyer un succès à Stripe mais avec des données JSON
            // que le front peut intercepter
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors du traitement du paiement',
                'redirect_url' => $this->stripeCancelUrl
            ], Response::HTTP_OK); // HTTP 200 car Stripe attend un succès
        }
    }
    
    #[Route('/api/webhook/stripe/status', name: 'api_webhook_stripe_status', methods: ['GET'])]
    public function checkWebhookStatus(Request $request): JsonResponse
    {
        $sessionId = $request->query->get('session_id');
        
        if (!$sessionId) {
            return new JsonResponse(['status' => 'error', 'message' => 'Session ID requis'], Response::HTTP_BAD_REQUEST);
        }
        
        // Rechercher dans la base de données le webhook associé à cette session
        try {
            $webhook = $this->entityManager->getRepository(StripeWebhookLog::class)
                ->createQueryBuilder('w')
                ->where('w.payload LIKE :sessionId')
                ->setParameter('sessionId', '%"id":"' . $sessionId . '"%')
                ->orderBy('w.createdAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            if (!$webhook) {
                // Webhook non trouvé - peut être en attente de traitement
                return new JsonResponse([
                    'status' => 'pending',
                    'message' => 'Paiement en cours de traitement'
                ]);
            }
            
            // Vérifier le statut du webhook
            if ($webhook->getStatus() === StripeWebhookLog::STATUS_SUCCESS) {
                return new JsonResponse([
                    'status' => 'success',
                    'message' => 'Paiement traité avec succès'
                ]);
            } elseif ($webhook->getStatus() === StripeWebhookLog::STATUS_ERROR) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Une erreur est survenue lors du traitement du paiement',
                    'error_details' => $webhook->getErrorMessage(),
                    'redirect_url' => $this->stripeCancelUrl
                ]);
            } else {
                // En cours de traitement ou ignoré
                return new JsonResponse([
                    'status' => $webhook->getStatus(),
                    'message' => 'Paiement en cours de traitement'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la vérification du statut du webhook', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors de la vérification du statut',
                'redirect_url' => $this->stripeCancelUrl
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}