<?php
declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

interface PaymentServiceInterface
{
    /**
     * Crée une session de paiement pour un utilisateur
     * 
     * @param array<string, mixed> $metadata Les métadonnées associées à la session
     * @return array<string, mixed> Les détails de la session créée
     */
    public function createSession(
        User $user,
        int $amount,
        string $currency = 'eur',
        array $metadata = []
    ): array;

    /**
     * Gère les webhooks de paiement
     * 
     * @param string $eventType Le type d'événement webhook
     * @param array<string, mixed> $eventData Les données de l'événement webhook
     * @return bool Succès ou échec du traitement
     */
    public function handleWebhook(string $eventType, array $eventData): bool;
}