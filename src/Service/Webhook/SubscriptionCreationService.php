<?php

declare(strict_types=1);

namespace App\Service\Webhook;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;

final readonly class SubscriptionCreationService
{
    public function __construct(
        private UserRepository $userRepository,
        private SubscriptionRepository $subscriptionRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Creates a subscription from webhook data
     */
    public function createFromWebhookData(array $eventData): ?Subscription
    {
        $stripeSubscriptionId = $eventData['id'] ?? null;
        $customerId = $eventData['customer'] ?? null;
        
        if (!$stripeSubscriptionId || !$customerId) {
            $this->logger->warning('Insufficient data to create subscription', [
                'subscription_id' => $stripeSubscriptionId,
                'customer_id' => $customerId
            ]);
            return null;
        }
        
        // Find the user corresponding to the Stripe customer
        $user = $this->userRepository->findOneByStripeCustomerId($customerId);
        
        if (!$user) {
            $this->logger->warning('User not found for Stripe customer', [
                'customer_id' => $customerId
            ]);
            return null;
        }
        
        // Check if subscription already exists
        if ($this->subscriptionRepository->findOneByStripeSubscriptionId($stripeSubscriptionId)) {
            return null;
        }
        
        // Create a new subscription
        $subscription = new Subscription();
        $subscription->setStripeSubscriptionId($stripeSubscriptionId)
            ->setStripeId($stripeSubscriptionId)
            ->setUser($user);
        
        $this->setSubscriptionDataFromWebhook($subscription, $eventData);
        
        // Save the subscription
        $this->subscriptionRepository->save($subscription);
        
        $this->logger->info('Subscription created from webhook data', [
            'subscription_id' => $subscription->getId()->toRfc4122(),
            'stripe_subscription_id' => $stripeSubscriptionId,
            'user_id' => $user->getId()
        ]);
        
        return $subscription;
    }

    /**
     * Sets subscription data from webhook
     */
    private function setSubscriptionDataFromWebhook(Subscription $subscription, array $eventData): void
    {
        // Set plan if available
        if (isset($eventData['plan']['id'])) {
            $subscription->setStripePlanId($eventData['plan']['id']);
        }
        
        // Set amount if available
        if (isset($eventData['plan']['amount'])) {
            $subscription->setAmount((int) $eventData['plan']['amount']);
        }
        
        // Set currency if available
        if (isset($eventData['plan']['currency'])) {
            $subscription->setCurrency($eventData['plan']['currency']);
        }
        
        // Set interval if available
        if (isset($eventData['plan']['interval'])) {
            $subscription->setInterval($eventData['plan']['interval']);
        }
        
        // Set status
        $status = match($eventData['status'] ?? 'active') {
            'active' => Subscription::STATUS_ACTIVE,
            'canceled' => Subscription::STATUS_CANCELED,
            'incomplete' => Subscription::STATUS_INCOMPLETE,
            'incomplete_expired' => Subscription::STATUS_CANCELED,
            'past_due' => Subscription::STATUS_PAST_DUE,
            'trialing' => Subscription::STATUS_TRIALING,
            'unpaid' => Subscription::STATUS_UNPAID,
            default => Subscription::STATUS_PENDING
        };
        $subscription->setStatus($status);
        
        // Set dates
        if (isset($eventData['current_period_start'])) {
            $startDate = new \DateTimeImmutable('@' . $eventData['current_period_start']);
            $subscription->setStartDate($startDate);
        }
        
        if (isset($eventData['current_period_end'])) {
            $endDate = new \DateTimeImmutable('@' . $eventData['current_period_end']);
            $subscription->setEndDate($endDate);
        }
    }
}