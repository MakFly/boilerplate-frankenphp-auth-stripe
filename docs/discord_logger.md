# Système de Logging Discord

Ce projet intègre un système de journalisation qui envoie des logs vers différents canaux Discord via webhooks.

## Configuration

Configurez vos webhooks Discord dans le fichier `.env`:

```dotenv
# Webhooks pour différents canaux
DISCORD_WEBHOOK_ERROR=https://discord.com/api/webhooks/123/abc
DISCORD_WEBHOOK_INFO=https://discord.com/api/webhooks/456/def
DISCORD_WEBHOOK_SYSTEM=https://discord.com/api/webhooks/789/ghi

# Paramètres par défaut (optionnels)
DISCORD_DEFAULT_USERNAME=Symfony Logger
DISCORD_DEFAULT_AVATAR_URL=https://example.com/avatar.png
```

> **Note:** Il est recommandé de configurer ces variables dans votre fichier `.env.local` plutôt que dans `.env` pour des raisons de sécurité.

### Canaux préconfigurés

Les canaux suivants sont préconfigurés:

| Canal | Description | Niveau par défaut (dev) | Niveau par défaut (prod) |
|-------|-------------|-------------------------|--------------------------|
| `discord_error` | Erreurs et exceptions | `error` | `error` |
| `discord_system` | Logs système et techniques | `info` | `error` |
| `discord_info` | Informations générales | `info` | `warning` |
| `discord` | Canal générique (rétrocompatibilité) | `info` | `error` |

## Utilisation

### Avec Autowiring (recommandé)

L'approche recommandée utilise les attributs de PHP 8.0+ pour l'autowiring:

```php
<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MonService
{
    public function __construct(
        #[Autowire('@monolog.logger.discord_error')]
        private readonly LoggerInterface $errorLogger,
        
        #[Autowire('@monolog.logger.discord_system')]
        private readonly LoggerInterface $systemLogger
    ) {
    }
    
    public function maMethode(): void
    {
        try {
            // Code métier...
            
            $this->systemLogger->info('Opération réussie', [
                'user_id' => 123,
                'timestamp' => new \DateTimeImmutable()
            ]);
        } catch (\Exception $e) {
            $this->errorLogger->error('Erreur lors du traitement', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Relancer l'exception après l'avoir loggée
        }
    }
}
```

### Avec injection de dépendances classique

Vous pouvez également utiliser l'injection via les services.yaml:

```yaml
# config/services.yaml
services:
    App\Service\MonService:
        arguments:
            $errorLogger: '@monolog.logger.discord_error'
            $systemLogger: '@monolog.logger.discord_system'
```

```php
<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class MonService
{
    public function __construct(
        private readonly LoggerInterface $errorLogger,
        private readonly LoggerInterface $systemLogger
    ) {
    }
    
    // Méthodes comme dans l'exemple précédent
}
```

## Bonnes pratiques de logging

### Niveaux de log appropriés

```php
// Du moins critique au plus critique
$logger->debug('Informations détaillées de débogage');
$logger->info('Événements normaux du système');
$logger->notice('Événements significatifs mais normaux');
$logger->warning('Situations potentiellement problématiques');
$logger->error('Erreurs d'exécution qui n'empêchent pas l'application de fonctionner');
$logger->critical('Composants indisponibles, exceptions inattendues');
$logger->alert('Action immédiate nécessaire');
$logger->emergency('Système inutilisable');
```

### Format des messages de log

Suivez ces recommandations pour des logs efficaces:

1. **Messages concis et descriptifs**: "Échec de paiement" plutôt que "Erreur"
2. **Contexte structuré**: Toujours inclure un tableau de contexte avec des informations pertinentes
3. **Identification unique**: Inclure des IDs uniques (utilisateur, transaction, etc.)
4. **Pas de données sensibles**: Ne jamais logger de mots de passe, tokens, etc.
5. **Standardisez les clés de contexte**: Utilisez toujours les mêmes noms de clés (user_id vs userId)

```php
// Bon exemple
$logger->error('Échec de paiement', [
    'user_id' => $user->getId(),
    'payment_id' => $payment->getId(),
    'amount' => $payment->getAmount(),
    'method' => $payment->getMethod(),
    'error_code' => $e->getCode()
]);

// Mauvais exemple
$logger->error('Erreur: ' . $e->getMessage());
```

## Ajouter un nouveau canal Discord

Pour ajouter un nouveau canal (ex: `audit`):

1. **Étape 1**: Ajoutez la variable d'environnement dans `.env` et `.env.example`:
   ```dotenv
   DISCORD_WEBHOOK_AUDIT=https://discord.com/api/webhooks/...
   ```

2. **Étape 2**: Modifiez `src/Logger/DiscordHandlerFactory.php` pour ajouter le nouveau webhook:
   ```php
   public function __construct(
       HttpClientInterface $httpClient,
       string $environment,
       string $errorWebhook = '',
       string $infoWebhook = '',
       string $systemWebhook = '',
       string $auditWebhook = '',  // Nouveau paramètre
       string $defaultUsername = 'Symfony Logger',
       string $defaultAvatarUrl = ''
   ) {
       // ... code existant ...
       
       if (!empty($auditWebhook)) {
           $this->webhooks['audit'] = [
               'url' => $auditWebhook,
               'username' => $defaultUsername,
               'avatar_url' => $defaultAvatarUrl
           ];
       }
   }
   ```

3. **Étape 3**: Mettez à jour la configuration du service dans `config/services.yaml`:
   ```yaml
   App\Logger\DiscordHandlerFactory:
       arguments:
           $httpClient: '@http_client'
           $environment: '%kernel.environment%'
           $errorWebhook: '%env(DISCORD_WEBHOOK_ERROR)%'
           $infoWebhook: '%env(DISCORD_WEBHOOK_INFO)%'
           $systemWebhook: '%env(DISCORD_WEBHOOK_SYSTEM)%'
           $auditWebhook: '%env(DISCORD_WEBHOOK_AUDIT)%'
           # ... autres arguments
   
   # Ajouter le handler
   app.monolog.discord_audit_handler:
       class: App\Logger\DiscordHandler
       factory: ['@App\Logger\DiscordHandlerFactory', 'createHandler']
       arguments: ['audit', !php/const Monolog\Level::Notice]
   ```

4. **Étape 4**: Ajoutez le canal dans `config/packages/monolog.yaml`:
   ```yaml
   monolog:
       channels:
           # ... channels existants
           - discord_audit
   
   when@dev:
       monolog:
           handlers:
               # ... handlers existants
               discord_audit:
                   type: service
                   id: app.monolog.discord_audit_handler
                   level: notice
                   channels: ["discord_audit"]
   
   when@prod:
       monolog:
           handlers:
               # ... handlers existants
               discord_audit:
                   type: service
                   id: app.monolog.discord_audit_handler
                   level: notice
                   channels: ["discord_audit"]
                   formatter: monolog.formatter.json
   ```

## Architecture du système

Le système utilise les composants suivants:

- **DiscordHandler**: Handler Monolog personnalisé qui envoie les logs à Discord
- **DiscordHandlerFactory**: Crée des handlers pour différents canaux et gère la configuration des webhooks 