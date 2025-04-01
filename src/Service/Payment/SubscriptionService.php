<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Subscription;
use App\Entity\User;
use App\Interface\PaymentServiceInterface;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\Invoice\InvoiceService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SubscriptionService implements PaymentServiceInterface
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_CANCELED = 'canceled';
    private const STATUS_INCOMPLETE = 'incomplete';
    private const STATUS_PAST_DUE = 'past_due';
    private const STATUS_UNPAID = 'unpaid'; 
    private const STATUS_TRIALING = 'trialing';
    
    private StripeClient $stripe;
    
    public function __construct(
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private string $stripeSecretKey,
        #[Autowire(env: 'STRIPE_SUCCESS_URL')]
        private string $successUrl,
        #[Autowire(env: 'STRIPE_CANCEL_URL')]
        private string $cancelUrl,
        private SubscriptionRepository $subscriptionRepository,
        private ?InvoiceService $invoiceService = null,
        private ?StripeClient $stripeClient = null,
        private ?LoggerInterface $logger = null,
        private ?UserRepository $userRepository = null
    ) {
        $this->stripe = $stripeClient ?? new StripeClient($this->stripeSecretKey);
    }

    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array
    {
        try {
            if (!isset($metadata['price_id'])) {
                throw new \InvalidArgumentException('Le price_id est requis pour créer un abonnement');
            }
            
            $this->logger?->info('Création d\'une session d\'abonnement', [
                'user_id' => $user->getId(),
                'price_id' => $metadata['price_id'],
                'amount' => $amount,
                'currency' => $currency
            ]);

            // retrieve customer on stripe with $user->getStripeCustomerId()
            $customer = $this->stripe->customers->retrieve($user->getStripeCustomerId());
            
            if (!$customer || isset($customer->deleted) && $customer->deleted) {
                // create customer on stripe
                $customer = $this->stripe->customers->create([
                    'email' => $user->getEmail(),
                    'name' => $user->getUsername(),
                ]);

                $user->setStripeCustomerId($customer->id);
                $this->userRepository->save($user);
            }
            
            // Création d'une session Stripe Checkout en mode subscription
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'customer' => $customer->id,
                'line_items' => [
                    [
                        'price' => $metadata['price_id'],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'metadata' => array_merge($metadata, [
                    'user_id' => $user->getId(),
                ]),
                'success_url' => $this->successUrl,
                'cancel_url' => $this->cancelUrl,
            ]);
            
            $this->logger?->debug('Session d\'abonnement créée', [
                'session_id' => $session->id,
                'url' => $session->url
            ]);
            
            return [
                'id' => $session->id,
                'url' => $session->url,
                'amount' => $amount,
                'currency' => $currency,
            ];
        } catch (ApiErrorException $e) {
            $this->logger?->error('Erreur lors de la création de la session d\'abonnement', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);
            throw new \RuntimeException('Erreur lors de la création de la session d\'abonnement: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $eventData Les données de l'événement webhook
     */
    public function handleWebhook(string $eventType, array $eventData): bool
    {
        $this->logger?->debug('Traitement d\'un webhook d\'abonnement', [
            'event_type' => $eventType
        ]);
        
        try {
            return match($eventType) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($eventData),
                'customer.subscription.created' => $this->handleSubscriptionCreated($eventData),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($eventData),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($eventData),
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($eventData),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($eventData),
                default => false
            };
        } catch (\Exception $e) {
            $this->logger?->error('Erreur lors du traitement du webhook', [
                'error' => $e->getMessage(),
                'event_type' => $eventType
            ]);
            return false;
        }
    }
    
    /**
     * Gère l'événement checkout.session.completed
     * 
     * @param array<string, mixed> $eventData
     */
    private function handleCheckoutCompleted(array $eventData): bool
    {
        $sessionId = $eventData['id'] ?? null;
        
        if (!$sessionId) {
            $this->logger?->warning('ID de session manquant dans l\'événement checkout.session.completed');
            return false;
        }
        
        $subscription = $this->subscriptionRepository->findOneByStripeId($sessionId);
        
        if (!$subscription) {
            $this->logger?->warning('Abonnement non trouvé pour la session', ['session_id' => $sessionId]);
            return false;
        }
        
        // L'abonnement est maintenant en statut "incomplet" jusqu'à la confirmation du paiement
        $subscription->setStatus(self::STATUS_INCOMPLETE);
        
        // Enregistrer l'ID d'abonnement Stripe si disponible
        if (isset($eventData['subscription'])) {
            $subscription->setStripeSubscriptionId($eventData['subscription']);
        }
        
        $this->subscriptionRepository->save($subscription);
        
        $this->logger?->info('Session d\'abonnement complétée', [
            'subscription_id' => $subscription->getId()
        ]);
        
        return true;
    }
    
    /**
     * Gère l'événement customer.subscription.created
     * 
     * @param array<string, mixed> $eventData
     */
    private function handleSubscriptionCreated(array $eventData): bool
    {
        $stripeSubscriptionId = $eventData['id'] ?? null;
        
        if (!$stripeSubscriptionId) {
            return false;
        }
        
        $subscription = $this->subscriptionRepository->findOneByStripeSubscriptionId($stripeSubscriptionId);
        
        if (!$subscription) {
            // C'est normal si l'abonnement a été créé par un autre moyen que notre application
            $this->logger?->info('Abonnement Stripe créé mais non trouvé dans notre base', [
                'stripe_subscription_id' => $stripeSubscriptionId
            ]);
            return false;
        }
        
        // Mise à jour des métadonnées
        if (isset($eventData['metadata'])) {
            $subscription->setMetadata($eventData['metadata']);
        }
        
        // Mise à jour du statut
        $status = $this->mapStripeStatus($eventData['status'] ?? '');
        $subscription->setStatus($status);
        
        // Mise à jour des dates
        if (isset($eventData['current_period_start'])) {
            $startDate = new \DateTimeImmutable('@' . $eventData['current_period_start']);
            $subscription->setStartDate($startDate);
        }
        
        if (isset($eventData['current_period_end'])) {
            $endDate = new \DateTimeImmutable('@' . $eventData['current_period_end']);
            $subscription->setEndDate($endDate);
        }
        
        $this->subscriptionRepository->save($subscription);
        
        $this->logger?->info('Abonnement Stripe créé', [
            'subscription_id' => $subscription->getId(),
            'status' => $status
        ]);
        
        return true;
    }
    
    /**
     * Gère l'événement customer.subscription.updated
     * 
     * @param array<string, mixed> $eventData
     */
    private function handleSubscriptionUpdated(array $eventData): bool
    {
        $stripeSubscriptionId = $eventData['id'] ?? null;
        
        if (!$stripeSubscriptionId) {
            return false;
        }
        
        $subscription = $this->subscriptionRepository->findOneByStripeSubscriptionId($stripeSubscriptionId);
        
        if (!$subscription) {
            $this->logger?->warning('Abonnement non trouvé pour la mise à jour', [
                'stripe_subscription_id' => $stripeSubscriptionId
            ]);
            return false;
        }
        
        // Mise à jour du statut
        $status = $this->mapStripeStatus($eventData['status'] ?? '');
        $subscription->setStatus($status);
        
        // Mise à jour des dates
        if (isset($eventData['current_period_start'])) {
            $startDate = new \DateTimeImmutable('@' . $eventData['current_period_start']);
            $subscription->setStartDate($startDate);
        }
        
        if (isset($eventData['current_period_end'])) {
            $endDate = new \DateTimeImmutable('@' . $eventData['current_period_end']);
            $subscription->setEndDate($endDate);
        }
        
        // Mise à jour du renouvellement automatique
        if (isset($eventData['cancel_at_period_end'])) {
            $subscription->setAutoRenew(!$eventData['cancel_at_period_end']);
        }
        
        $this->subscriptionRepository->save($subscription);
        
        $this->logger?->info('Abonnement Stripe mis à jour', [
            'subscription_id' => $subscription->getId(),
            'status' => $status
        ]);
        
        return true;
    }
    
    /**
     * Gère l'événement customer.subscription.deleted
     * 
     * @param array<string, mixed> $eventData
     */
    private function handleSubscriptionDeleted(array $eventData): bool
    {
        $stripeSubscriptionId = $eventData['id'] ?? null;
        
        if (!$stripeSubscriptionId) {
            return false;
        }
        
        $subscription = $this->subscriptionRepository->findOneByStripeSubscriptionId($stripeSubscriptionId);
        
        if (!$subscription) {
            $this->logger?->warning('Abonnement non trouvé pour la suppression', [
                'stripe_subscription_id' => $stripeSubscriptionId
            ]);
            return false;
        }
        
        // Marquer l'abonnement comme annulé
        $subscription->setStatus(self::STATUS_CANCELED);
        $subscription->setCanceledAt(new \DateTimeImmutable());
        $subscription->setAutoRenew(false);
        
        $this->subscriptionRepository->save($subscription);
        
        $this->logger?->info('Abonnement Stripe supprimé', [
            'subscription_id' => $subscription->getId()
        ]);
        
        return true;
    }
    
    /**
     * Gère l'événement invoice.payment_succeeded
     * 
     * @param array<string, mixed> $eventData
     */
    private function handleInvoicePaymentSucceeded(array $eventData): bool
    {
        $stripeSubscriptionId = $eventData['subscription'] ?? null;
        $stripeInvoiceId = $eventData['id'] ?? null;
        $billingReason = $eventData['billing_reason'] ?? null;
        
        if (!$stripeSubscriptionId) {
            return false;
        }
        
        $subscription = $this->subscriptionRepository->findOneByStripeSubscriptionId($stripeSubscriptionId);
        
        if (!$subscription) {
            $this->logger?->warning('Abonnement non trouvé pour la facturation réussie', [
                'stripe_subscription_id' => $stripeSubscriptionId
            ]);
            return false;
        }
        
        // Mettre à jour le statut de l'abonnement
        $subscription->setStatus(self::STATUS_ACTIVE);
        $subscription->setLastErrorMessage(null);
        $subscription->setRetryCount(0);
        
        // Si c'est un renouvellement d'abonnement, mettre à jour la date de fin
        if ($billingReason === 'subscription_cycle') {
            $endDate = $subscription->getEndDate();
            
            if ($endDate) {
                // Ajouter une période selon l'intervalle de l'abonnement
                $interval = $subscription->getInterval();
                if ($interval === 'month') {
                    $newEndDate = $endDate->modify('+1 month');
                } elseif ($interval === 'year') {
                    $newEndDate = $endDate->modify('+1 year');
                } else {
                    $newEndDate = $endDate->modify('+1 month'); // Par défaut
                }
                
                $subscription->setEndDate($newEndDate);
                
                $this->logger?->info('Date de fin d\'abonnement mise à jour', [
                    'subscription_id' => $subscription->getId(),
                    'old_end_date' => $endDate->format('Y-m-d'),
                    'new_end_date' => $newEndDate->format('Y-m-d')
                ]);
            }
        }
        
        $this->subscriptionRepository->save($subscription);
        
        // Créer une facture si le service est disponible
        if ($this->invoiceService !== null && $stripeInvoiceId) {
            try {
                $this->invoiceService->createInvoiceForSubscription($subscription, $stripeInvoiceId);
                $this->logger?->info('Facture générée pour l\'abonnement', [
                    'subscription_id' => $subscription->getId(),
                    'invoice_id' => $stripeInvoiceId
                ]);
            } catch (\Exception $e) {
                $this->logger?->error('Erreur lors de la création de la facture', [
                    'error' => $e->getMessage(),
                    'subscription_id' => $subscription->getId()
                ]);
            }
        }
        
        $this->logger?->info('Paiement d\'abonnement réussi', [
            'subscription_id' => $subscription->getId()
        ]);
        
        return true;
    }
    
    /**
     * Gère l'événement invoice.payment_failed
     * 
     * @param array<string, mixed> $eventData
     */
    private function handleInvoicePaymentFailed(array $eventData): bool
    {
        $stripeSubscriptionId = $eventData['subscription'] ?? null;
        
        if (!$stripeSubscriptionId) {
            return false;
        }
        
        $subscription = $this->subscriptionRepository->findOneByStripeSubscriptionId($stripeSubscriptionId);
        
        if (!$subscription) {
            $this->logger?->warning('Abonnement non trouvé pour la facturation échouée', [
                'stripe_subscription_id' => $stripeSubscriptionId
            ]);
            return false;
        }
        
        // Mise à jour du statut (past_due ou unpaid selon la configuration de Stripe)
        $subscription->setStatus(self::STATUS_PAST_DUE);
        $subscription->incrementRetryCount();
        
        // Enregistrer le message d'erreur
        if (isset($eventData['last_payment_error']['message'])) {
            $subscription->setLastErrorMessage($eventData['last_payment_error']['message']);
        } else {
            $subscription->setLastErrorMessage('Échec du paiement de la facture');
        }
        
        $this->subscriptionRepository->save($subscription);
        
        $this->logger?->warning('Échec du paiement d\'abonnement', [
            'subscription_id' => $subscription->getId(),
            'retry_count' => $subscription->getRetryCount()
        ]);
        
        return true;
    }
    
    /**
     * Convertit un statut Stripe en statut d'application
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match($stripeStatus) {
            'active' => self::STATUS_ACTIVE,
            'canceled' => self::STATUS_CANCELED,
            'incomplete' => self::STATUS_INCOMPLETE,
            'incomplete_expired' => self::STATUS_CANCELED,
            'past_due' => self::STATUS_PAST_DUE,
            'trialing' => self::STATUS_TRIALING,
            'unpaid' => self::STATUS_UNPAID,
            default => self::STATUS_PENDING
        };
    }
    
    /**
     * Trouve les abonnements restés en pending avant une date donnée
     * 
     * @param \DateTimeInterface $date Date limite
     * @return array<Subscription> Abonnements en pending
     */
    public function findPendingSubscriptionsBeforeDate(\DateTimeInterface $date): array
    {
        return $this->subscriptionRepository->findPendingBeforeDate($date);
    }
    
    /**
     * Nettoie les abonnements restés en pending trop longtemps
     * 
     * @param int $hours Nombre d'heures après lequel un abonnement pending est considéré comme abandonné
     * @return int Nombre d'abonnements nettoyés
     */
    public function cleanPendingSubscriptions(int $hours = 24): int
    {
        $cutoffDate = new \DateTimeImmutable('now - ' . $hours . ' hours');
        $pendingSubscriptions = $this->subscriptionRepository->findPendingBeforeDate($cutoffDate);
        
        $cleanedCount = 0;
        
        foreach ($pendingSubscriptions as $subscription) {
            // Vérifier si une session Stripe existe toujours
            $stripeId = $subscription->getStripeId();
            
            if ($stripeId) {
                try {
                    // Vérifier l'état de la session Stripe
                    $session = $this->stripe->checkout->sessions->retrieve($stripeId);
                    
                    if ($session->status === 'complete') {
                        // La session a été complétée mais notre webhook a échoué
                        $subscription->setStatus(self::STATUS_INCOMPLETE);
                        $this->subscriptionRepository->save($subscription);
                        $this->logger?->info('Session complétée mais non traitée, marquée comme incomplete', [
                            'subscription_id' => $subscription->getId(),
                            'stripe_id' => $stripeId
                        ]);
                        continue;
                    } elseif ($session->status === 'open') {
                        // Session toujours active, mais trop ancienne
                        $subscription->setStatus(self::STATUS_CANCELED);
                        $subscription->setCanceledAt(new \DateTimeImmutable());
                        $this->subscriptionRepository->save($subscription);
                        $cleanedCount++;
                    }
                } catch (\Exception $e) {
                    // Session non trouvée ou autre erreur
                    $subscription->setStatus(self::STATUS_CANCELED);
                    $subscription->setCanceledAt(new \DateTimeImmutable());
                    $subscription->setLastErrorMessage($e->getMessage());
                    $this->subscriptionRepository->save($subscription);
                    $cleanedCount++;
                }
            } else {
                // Pas de session Stripe associée, annuler l'abonnement
                $subscription->setStatus(self::STATUS_CANCELED);
                $subscription->setCanceledAt(new \DateTimeImmutable());
                $this->subscriptionRepository->save($subscription);
                $cleanedCount++;
            }
        }
        
        $this->logger?->info('Nettoyage des abonnements pending terminé', [
            'cleaned_count' => $cleanedCount,
            'total_checked' => count($pendingSubscriptions)
        ]);
        
        return $cleanedCount;
    }
    
    /**
     * Annule un abonnement
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true): bool
    {
        try {
            $stripeSubscriptionId = $subscription->getStripeSubscriptionId();
            
            if (!$stripeSubscriptionId) {
                $this->logger?->warning('Impossible d\'annuler un abonnement sans ID Stripe', [
                    'subscription_id' => $subscription->getId()
                ]);
                return false;
            }
            
            $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'cancel_at_period_end' => $atPeriodEnd
            ]);
            
            if (!$atPeriodEnd) {
                $subscription->setStatus(self::STATUS_CANCELED);
                $subscription->setCanceledAt(new \DateTimeImmutable());
            }
            
            $subscription->setAutoRenew(false);
            $this->subscriptionRepository->save($subscription);
            
            $this->logger?->info('Abonnement annulé', [
                'subscription_id' => $subscription->getId(),
                'at_period_end' => $atPeriodEnd
            ]);
            
            return true;
        } catch (ApiErrorException $e) {
            $this->logger?->error('Erreur lors de l\'annulation de l\'abonnement', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->getId()
            ]);
            return false;
        }
    }
}