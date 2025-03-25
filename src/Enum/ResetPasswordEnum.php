<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Messages pour la réinitialisation de mot de passe
 * 
 * Contient les messages utilisés dans le processus de réinitialisation
 */
enum ResetPasswordEnum: string
{
    case RESET_PASSWORD_REQUEST_SENT = 'Le lien de réinitialisation de mot de passe a été envoyé à votre email';
    case RESET_PASSWORD_SUCCESS = 'Le mot de passe a été réinitialisé avec succès';
    case INVALID_TOKEN = 'Le token est invalide ou expiré';
    case TOKEN_ALREADY_SENT = 'Une demande de réinitialisation a déjà été faite, merci de regarder vos emails ou vos spam';
    case TOKEN_EXPIRED = 'Le token a expiré, merci de faire une nouvelle demande';

    /**
     * Obtient le libellé du message
     *
     * @return string Le texte du message
     */
    public function getLabel(): string
    {
        return $this->value;
    }
}
