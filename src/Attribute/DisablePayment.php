<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

/**
 * Attribut permettant de désactiver le système de paiement pour une classe ou une méthode.
 * Cet attribut peut être utilisé sur des contrôleurs ou des méthodes spécifiques.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class DisablePayment
{
    public function __construct(
        private readonly ?string $reason = null
    ) {
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}