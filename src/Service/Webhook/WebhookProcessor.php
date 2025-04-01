<?php

declare(strict_types=1);

namespace App\Service\Webhook;

use App\Entity\Subscription;
use App\Entity\StripeWebhookLog;
use App\Repository\StripeWebhookLogRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\Invoice\InvoiceService;
use App\Service\Payment\PaymentIntentService;
use App\Service\Payment\PaymentServiceFactory;
use App\Service\Payment\SubscriptionService;
use Psr\Log\LoggerInterface;
use Stripe\Event;

final readonly class WebhookProcessor
{
    public function __construct(
        private StripeWebhookLogRepository $webhookLogRepository,
        private SubscriptionRepository $subscriptionRepository,
        private UserRepository $userRepository,
        private PaymentServiceFactory $paymentServiceFactory,
        private SubscriptionService $subscriptionService,
        private PaymentIntentService $paymentIntentService,
        private InvoiceService $invoiceService,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Traite un événement webhook Stripe
     * 
     * @param Event $event L'événement Stripe
     * @param array<string, mixed> $eventData Les données de l'événement
     * @return StripeWebhookLog Le log de l'événement
     */
    public function processEvent(Event $event, array $eventData): StripeWebhookLog
    {
        $stripeEventId = $event->id;
        $eventType = $event->type;
        
        // Journaliser l'événement
        $this->logger->info('Traitement d\'un événement webhook Stripe', [
            'event_id' => $stripeEventId,
            'event_type' => $eventType,
        ]);
        
        // Créer une entrée de journal
        $log = new StripeWebhookLog();
        $log->setEventId($stripeEventId)
            ->setEventType($eventType)
            ->setPayload($eventData)
            ->setProcessorType($this->paymentServiceFactory->determineProcessorTypeFromEvent($eventType, $eventData))
            ->setStatus(StripeWebhookLog::STATUS_PROCESSING);
        
        $this->webhookLogRepository->save($log);
        
        try {
            // Déterminer le processeur approprié et traiter l'événement
            $processorType = $log->getProcessorType();
            $success = false;
            
            // Obtenir le service de paiement approprié via la factory
            $paymentService = $this->paymentServiceFactory->create($processorType);
            $success = $paymentService->handleWebhook($eventType, $eventData);
            
            if ($success) {
                $log->setStatus(StripeWebhookLog::STATUS_SUCCESS);
                $this->logger->info('Événement webhook traité avec succès', [
                    'event_id' => $stripeEventId,
                    'event_type' => $eventType,
                ]);
            } else {
                $log->setStatus(StripeWebhookLog::STATUS_IGNORED);
                $this->logger->info('Événement webhook ignoré', [
                    'event_id' => $stripeEventId,
                    'event_type' => $eventType,
                ]);
            }
        } catch (\Exception $e) {
            $log->setStatus(StripeWebhookLog::STATUS_ERROR)
                ->setErrorMessage($e->getMessage());
            
            $this->logger->error('Erreur lors du traitement de l\'événement webhook', [
                'event_id' => $stripeEventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        $this->webhookLogRepository->save($log);
        return $log;
    }
    
    /**
     * Crée un abonnement à partir des données d'un webhook
     * 
     * @param array<string, mixed> $eventData Les données du webhook
     */
    private function createSubscriptionFromWebhook(array $eventData): void
    {
        $stripeSubscriptionId = $eventData['id'] ?? null;
        $customerId = $eventData['customer'] ?? null;
        
        if (!$stripeSubscriptionId || !$customerId) {
            $this->logger->warning('Données insuffisantes pour créer un abonnement', [
                'subscription_id' => $stripeSubscriptionId,
                'customer_id' => $customerId
            ]);
            return;
        }
        
        // Trouver l'utilisateur correspondant au client Stripe
        $user = $this->userRepository->findOneByStripeCustomerId($customerId);
        
        if (!$user) {
            $this->logger->warning('Utilisateur non trouvé pour le client Stripe', [
                'customer_id' => $customerId
            ]);
            return;
        }
        
        // Créer un nouvel abonnement
        $subscription = new Subscription();
        $subscription->setStripeSubscriptionId($stripeSubscriptionId)
            ->setStripeId($stripeSubscriptionId)
            ->setUser($user);
        
        // Définir le plan si disponible
        if (isset($eventData['plan']['id'])) {
            $subscription->setStripePlanId($eventData['plan']['id']);
        }
        
        // Définir le montant si disponible
        if (isset($eventData['plan']['amount'])) {
            $subscription->setAmount((int) $eventData['plan']['amount']);
        }
        
        // Définir la devise si disponible
        if (isset($eventData['plan']['currency'])) {
            $subscription->setCurrency($eventData['plan']['currency']);
        }
        
        // Définir l'intervalle si disponible
        if (isset($eventData['plan']['interval'])) {
            $subscription->setInterval($eventData['plan']['interval']);
        }
        
        // Définir le statut
        $status = 'active';
        if (isset($eventData['status'])) {
            $status = match($eventData['status']) {
                'active' => Subscription::STATUS_ACTIVE,
                'canceled' => Subscription::STATUS_CANCELED,
                'incomplete' => Subscription::STATUS_INCOMPLETE,
                'incomplete_expired' => Subscription::STATUS_CANCELED,
                'past_due' => Subscription::STATUS_PAST_DUE,
                'trialing' => Subscription::STATUS_TRIALING,
                'unpaid' => Subscription::STATUS_UNPAID,
                default => Subscription::STATUS_PENDING
            };
        }
        $subscription->setStatus($status);
        
        // Définir les dates
        if (isset($eventData['current_period_start'])) {
            $startDate = new \DateTimeImmutable('@' . $eventData['current_period_start']);
            $subscription->setStartDate($startDate);
        }
        
        if (isset($eventData['current_period_end'])) {
            $endDate = new \DateTimeImmutable('@' . $eventData['current_period_end']);
            $subscription->setEndDate($endDate);
        }
        
        // Sauvegarder l'abonnement
        $this->subscriptionRepository->save($subscription);
        
        $this->logger->info('Abonnement créé à partir des données webhook', [
            'subscription_id' => $subscription->getId()->toRfc4122(),
            'stripe_subscription_id' => $stripeSubscriptionId,
            'user_id' => $user->getId()
        ]);
    }
    
    /**
     * Retraite les webhooks en erreur
     * 
     * @return int Le nombre de webhooks retraités avec succès
     */
    public function retryFailedWebhooks(int $limit = 10): int
    {
        $failedWebhooks = $this->webhookLogRepository->findErrors($limit);
        $successCount = 0;
        
        foreach ($failedWebhooks as $webhookLog) {
            // Incrémenter le compteur de tentatives
            $webhookLog->incrementRetryCount();
            
            try {
                // Obtenir les données du webhook
                $eventData = $webhookLog->getPayload();
                $eventType = $webhookLog->getEventType();
                $processorType = $webhookLog->getProcessorType();
                
                // Si c'est un événement de création d'abonnement et qu'il n'existe pas,
                // essayer de le créer automatiquement
                if ($eventType === 'customer.subscription.created') {
                    $stripeSubscriptionId = $eventData['id'] ?? null;
                    if ($stripeSubscriptionId && !$this->subscriptionRepository->findOneByStripeSubscriptionId($stripeSubscriptionId)) {
                        $this->createSubscriptionFromWebhook($eventData);
                    }
                }
                
                // Retraiter l'événement avec le service approprié
                $paymentService = $this->paymentServiceFactory->create($processorType);
                $success = $paymentService->handleWebhook($eventType, $eventData);
                
                if ($success) {
                    $webhookLog->markAsSuccess();
                    $successCount++;
                } else {
                    $webhookLog->markAsIgnored('Événement non traité lors de la nouvelle tentative');
                }
            } catch (\Exception $e) {
                // Mettre à jour le message d'erreur
                $webhookLog->markAsError($e->getMessage(), [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                    'retry_count' => $webhookLog->getRetryCount()
                ]);
            }
            
            // Mettre à jour le log
            $this->webhookLogRepository->save($webhookLog);
        }
        
        return $successCount;
    }
} 