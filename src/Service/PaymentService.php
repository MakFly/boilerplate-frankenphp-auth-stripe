<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Subscription;

class PaymentService
{
    private const TVA_RATE = 0.20; // 20% TVA

    public function calculateTotalAmount(Invoice $invoice): int
    {
        $tax = (int)round($invoice->getAmount() * self::TVA_RATE);
        return $invoice->getAmount() + $tax;
    }

    public function isPaymentCompleted(Payment $payment): bool
    {
        return $payment->getStatus() === 'completed';
    }

    public function calculateNextRenewalDate(Subscription $subscription): \DateTimeImmutable
    {
        $nextRenewal = $subscription->getStartDate();
        
        return match ($subscription->getInterval()) {
            'month' => $nextRenewal->modify('+1 month'),
            'year' => $nextRenewal->modify('+1 year'),
            default => throw new \InvalidArgumentException('Intervalle invalide')
        };
    }
}