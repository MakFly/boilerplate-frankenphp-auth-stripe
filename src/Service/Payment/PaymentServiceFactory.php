<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Interface\PaymentServiceInterface;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\Invoice\InvoiceService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class PaymentServiceFactory
{
    private const TYPE_PAYMENT_INTENT = 'payment_intent';
    private const TYPE_SUBSCRIPTION = 'subscription';

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly PaymentRepository $paymentRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly UserRepository $userRepository,
        private readonly ?InvoiceService $invoiceService = null,
    ) {
    }

    public function create(string $type): PaymentServiceInterface
    {
        $baseService = match($type) {
            self::TYPE_PAYMENT_INTENT => new PaymentIntentService(
                $this->params->get('stripe_secret_key'),
                $this->params->get('stripe_success_url'),
                $this->params->get('stripe_cancel_url'),
                $this->paymentRepository,
                $this->invoiceService
            ),
            self::TYPE_SUBSCRIPTION => new SubscriptionService(
                $this->params->get('stripe_secret_key'),
                $this->params->get('stripe_public_key'),
                $this->params->get('stripe_success_url'),
                $this->params->get('stripe_cancel_url'),
                $this->subscriptionRepository
            ),
            default => throw new \InvalidArgumentException("Type de service de paiement non reconnu: $type"),
        };

        // Application du décorateur à tous les services de paiement
        return new PaymentLoggerDecorator(
            $baseService,
            $this->paymentRepository,
            $this->subscriptionRepository,
            $this->userRepository
        );
    }
}