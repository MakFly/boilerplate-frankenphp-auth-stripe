<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\User;
use App\Interface\PaymentServiceInterface;
use App\Repository\SubscriptionRepository;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

final readonly class SubscriptionService implements PaymentServiceInterface
{
    private const PAYMENT_TYPE = 'subscription';
    private const STATUS_PENDING = 'pending';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_CANCELED = 'canceled';
    
    private StripeClient $stripe;

    public function __construct(
        private string $stripeSecretKey,
        private string $stripePublicKey,
        private string $successUrl,
        private string $cancelUrl,
        private SubscriptionRepository $subscriptionRepository,
        ?StripeClient $stripeClient = null
    ) {
        $this->stripe = $stripeClient ?? new StripeClient($this->stripeSecretKey);
    }

    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array
    {
        try {
            // On suppose que le prix est déjà créé dans Stripe
            $priceId = $metadata['price_id'] ?? null;
            
            if (!$priceId) {
                throw new \InvalidArgumentException('Le price_id est requis pour créer un abonnement');
            }
            
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'customer_email' => $user->getEmail(),
                'line_items' => [
                    [
                        'price' => $priceId,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'success_url' => $this->successUrl,
                'cancel_url' => $this->cancelUrl,
                'metadata' => array_merge($metadata, [
                    'user_id' => $user->getId(),
                ]),
            ]);
            
            return [
                'id' => $session->id,
                'url' => $session->url,
                'amount' => $amount,
                'currency' => $currency,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Erreur lors de la création de la session d\'abonnement: ' . $e->getMessage());
        }
    }

    /**
     * @param array{subscription?: string, customer?: string, billing_reason?: string} $eventData Les données de l'événement webhook
     */
    public function handleWebhook(string $eventType, array $eventData): bool
    {
        $subscriptionId = match ($eventType) {
            'checkout.session.completed' => $eventData['subscription'] ?? null,
            'customer.subscription.deleted', 'invoice.payment_succeeded' => $eventData['subscription'] ?? null,
            default => null,
        };
        
        if (!$subscriptionId) {
            return false;
        }
        
        $subscription = $this->subscriptionRepository->findOneByStripeId($subscriptionId);
        
        if (!$subscription) {
            return false;
        }
        
        switch ($eventType) {
            case 'checkout.session.completed':
                $subscription->setStatus(self::STATUS_ACTIVE);
                // Si on a un nouveau customerId, on peut le sauvegarder
                if (isset($eventData['customer'])) {
                    $subscription->getUser()->setStripeCustomerId($eventData['customer']);
                }
                break;
                
            case 'customer.subscription.deleted':
                $subscription->setStatus(self::STATUS_CANCELED);
                break;
                
            case 'invoice.payment_succeeded':
                // Vérifier que c'est un renouvellement
                if (($eventData['billing_reason'] ?? '') === 'subscription_cycle') {
                    // Calculer la nouvelle date de fin en fonction de l'intervalle
                    $interval = $subscription->getInterval() ?? 'month';
                    $currentEndDate = $subscription->getStartDate() ?? new \DateTimeImmutable();
                    $subscription->setEndDate($currentEndDate->modify('+1 ' . $interval));
                }
                break;
                
            default:
                return false;
        }
        
        $this->subscriptionRepository->save($subscription);
        return true;
    }
}