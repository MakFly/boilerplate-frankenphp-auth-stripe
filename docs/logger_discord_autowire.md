# Logger Discord avec Autowiring

Ce guide présente un exemple simple d'utilisation des loggers Discord avec l'autowiring dans un service Symfony.

## Configuration simple

Configurez les webhooks dans `.env.local`:

```dotenv
# Webhooks pour différents canaux (à remplacer par vos URLs)
DISCORD_WEBHOOK_ERROR=https://discord.com/api/webhooks/...
DISCORD_WEBHOOK_SYSTEM=https://discord.com/api/webhooks/...
DISCORD_WEBHOOK_INFO=https://discord.com/api/webhooks/...
```

## Exemple d'utilisation avec Autowiring

```php
<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MonService
{
    public function __construct(
        // Pour les erreurs et exceptions
        #[Autowire('@monolog.logger.discord_error')]
        private readonly LoggerInterface $errorLogger,
        
        // Pour les logs système et techniques
        #[Autowire('@monolog.logger.discord_system')]
        private readonly LoggerInterface $systemLogger,
        
        // Pour les logs d'information
        #[Autowire('@monolog.logger.discord_info')]
        private readonly LoggerInterface $infoLogger
    ) {
    }
    
    public function processerDonnees(array $donnees): void
    {
        // Log d'information
        $this->infoLogger->info('Traitement des données démarré', [
            'nombre_entrees' => count($donnees),
            'date' => new \DateTimeImmutable()
        ]);
        
        try {
            // Votre logique métier
            
            // Log système
            $this->systemLogger->notice('Traitement terminé avec succès', [
                'entrees_traitees' => count($donnees),
                'duree_ms' => 234 // exemple
            ]);
        }
        catch (\Exception $e) {
            // Log d'erreur
            $this->errorLogger->error('Échec du traitement', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
```

## Canaux disponibles

| Canal | Service | Description | Niveau minimal |
|-------|---------|-------------|----------------|
| Erreurs | `@monolog.logger.discord_error` | Erreurs et exceptions | `error` |
| Système | `@monolog.logger.discord_system` | Logs système | `info` (dev), `error` (prod) |
| Info | `@monolog.logger.discord_info` | Logs généraux | `info` (dev), `warning` (prod) |

## Astuces

- Utilisez le niveau de log approprié (`debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`)
- Fournissez toujours un contexte structuré (tableau associatif)
- Ne loggez jamais d'informations sensibles (mots de passe, tokens, etc.)
- Pour plus de détails, consultez la documentation complète dans `docs/discord_logger.md` 