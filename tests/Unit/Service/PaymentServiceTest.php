<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Service\PaymentService;

beforeEach(function () {
    $this->paymentService = new PaymentService();
});

test('le calcul du montant total est correct', function () {
    $invoice = new Invoice();
    $invoice->setAmount(10000); // 100.00 €

    $totalAmount = $this->paymentService->calculateTotalAmount($invoice);

    expect($totalAmount)->toBe(12000); // 100.00 € + 20% TVA = 120.00 €
});

test('la vérification du statut du paiement fonctionne', function () {
    $payment = new Payment();
    $payment->setStatus('completed');

    expect($this->paymentService->isPaymentCompleted($payment))->toBeTrue();

    $payment->setStatus('pending');
    expect($this->paymentService->isPaymentCompleted($payment))->toBeFalse();
});

test('le calcul du prochain renouvellement est correct', function () {
    $subscription = new Subscription();
    $subscription->setStartDate(new \DateTimeImmutable('2025-01-01'));
    $subscription->setInterval('month');

    $nextRenewal = $this->paymentService->calculateNextRenewalDate($subscription);

    expect($nextRenewal->format('Y-m-d'))->toBe('2025-02-01');
});