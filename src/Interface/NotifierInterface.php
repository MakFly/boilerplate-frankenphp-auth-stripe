<?php
declare(strict_types=1);

namespace App\Interface;

interface NotifierInterface
{
    /**
     * Envoie une notification
     * 
     * @param string $recipient Le destinataire de la notification
     * @param string $subject Le sujet de la notification
     * @param string|array<string, mixed> $content Le contenu de la notification
     * @param array<string, mixed> $options Options spécifiques au canal de notification
     * 
     * @return bool Succès ou échec de l'envoi
     */
    public function send(string $recipient, string $subject, string|array $content, array $options = []): bool;
}