<?php
declare(strict_types=1);

namespace App\Interface;

interface NotifierInterface
{
    /**
     * Envoie une notification
     * 
     * @param string $to Adresse email du destinataire
     * @param string $subject Sujet de la notification
     * @param array<string, mixed> $content Le contenu à envoyer dans la notification
     * @param string $template Le template à utiliser pour le rendu
     */
    public function send(string $to, string $subject, array $content, string $template): void;
}