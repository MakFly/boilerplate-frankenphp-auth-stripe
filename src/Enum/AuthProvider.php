<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Fournisseurs d'authentification
 * 
 * Définit les différentes méthodes d'authentification supportées
 */
enum AuthProvider: string
{
    case CREDENTIALS = 'credentials';
    case GOOGLE = 'google';

    /**
     * Obtient un libellé plus lisible pour l'affichage
     *
     * @return string Libellé formaté du fournisseur d'authentification
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CREDENTIALS => 'Credentials',
            self::GOOGLE => 'Google',
        };
    }
}
