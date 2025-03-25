<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Interface\PaymentServiceInterface;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;

final readonly class PaymentLoggerDecorator implements PaymentServiceInterface
{
    public function __construct(
        private PaymentServiceInterface $paymentService,
        private PaymentRepository $paymentRepository,
        private SubscriptionRepository $subscriptionRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array
    {
        $sessionData = $this->paymentService->createSession($user, $amount, $currency, $metadata);
        
        $serviceClass = get_class($this->paymentService);
        
        if ($serviceClass === PaymentIntentService::class) {
            $payment = new Payment();
            $payment->setStripeId($sessionData['id'])
                ->setAmount($amount)
                ->setCurrency($currency)
                ->setStatus('pending')
                ->setPaymentType('payment_intent')
                ->setUser($user);
            
            if (isset($metadata['description'])) {
                $payment->setDescription($metadata['description']);
            }
            
            $this->paymentRepository->save($payment);
        } elseif ($serviceClass === SubscriptionService::class) {
            $subscription = new Subscription();
            $subscription->setStripeId($sessionData['id'])
                ->setStripePlanId($metadata['price_id'])
                ->setAmount($amount)
                ->setCurrency($currency)
                ->setStatus('pending')
                ->setInterval($metadata['interval'] ?? 'month')
                ->setUser($user);
            
            $this->subscriptionRepository->save($subscription);
        }
        
        return $sessionData;
    }

    public function handleWebhook(string $eventType, array $eventData): bool
    {
        return $this->paymentService->handleWebhook($eventType, $eventData);
    }
}