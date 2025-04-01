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
use Psr\Log\LoggerInterface;

final readonly class PaymentLoggerDecorator implements PaymentServiceInterface
{
    public function __construct(
        private PaymentServiceInterface $paymentService,
        private PaymentRepository $paymentRepository,
        private SubscriptionRepository $subscriptionRepository,
        private UserRepository $userRepository,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function createSession(User $user, int $amount, string $currency = 'eur', array $metadata = []): array
    {
        $sessionData = $this->paymentService->createSession($user, $amount, $currency, $metadata);
        
        $serviceClass = get_class($this->paymentService);
        
        if ($this->logger) {
            $this->logger->info('Session de paiement créée', [
                'service_class' => $serviceClass,
                'session_id' => $sessionData['id'],
                'user_id' => $user->getId(),
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => $metadata
            ]);
        }
        
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
        if ($this->logger) {
            $this->logger->debug('PaymentLoggerDecorator: handling webhook', [
                'event_type' => $eventType,
                'event_data_keys' => array_keys($eventData),
                'decorated_service' => get_class($this->paymentService)
            ]);
        }
        
        $result = $this->paymentService->handleWebhook($eventType, $eventData);
        
        if ($this->logger) {
            $this->logger->debug('PaymentLoggerDecorator: webhook result', [
                'result' => $result,
                'event_type' => $eventType,
                'service_class' => get_class($this->paymentService)
            ]);
        }
        
        return $result;
    }
}