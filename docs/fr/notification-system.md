# Documentation: Système de Notification Générique pour Symfony

## Introduction

Ce document présente l'implémentation d'un système de notification générique pour Symfony, basé sur le pattern Stratégie, le pattern Décorateur et le pattern Factory. Cette architecture permet d'envoyer facilement des notifications via différents canaux (email, SMS, notifications push, etc.) tout en maintenant une interface cohérente et extensible.

## Architecture du Système

Le système est structuré autour de plusieurs composants clés:

1. **NotifierInterface**: Définit le contrat pour tous les services de notification
2. **Notificateurs spécifiques**: Implémentations pour chaque canal de notification
3. **NotifierFactory**: Crée les instances de notificateurs appropriés
4. **NotificationManager**: Façade qui coordonne l'envoi des notifications

### Diagramme de Classes

```
NotifierInterface
       ↑
       |
       ├─── EmailNotifier
       ├─── SmsNotifier
       └─── [Autres notificateurs]
       
NotifierFactory ────► NotificationManager
```

## Implémentation

### 1. Interface NotifierInterface

```php
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
     * @param string|array $content Le contenu de la notification
     * @param array<string, mixed> $options Options spécifiques au canal de notification
     * 
     * @return bool Succès ou échec de l'envoi
     */
    public function send(string $recipient, string $subject, string|array $content, array $options = []): bool;
}
```

### 2. Implémentation des Notificateurs

#### EmailNotifier

```php
<?php

declare(strict_types=1);

namespace App\Service\Notifier;

use App\Interface\NotifierInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final readonly class EmailNotifier implements NotifierInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        #[Autowire('%mailer_from%')]
        private string $mailerFrom,
        private LoggerInterface $logger
    ) {
    }

    public function send(string $recipient, string $subject, string|array $content, array $options = []): bool
    {
        try {
            $email = new Email();
            $email->from($this->mailerFrom)
                ->to($recipient)
                ->subject($subject);

            // Extraction du template des options
            $template = $options['template'] ?? null;

            if ($template) {
                $html = $this->twig->render($template, is_array($content) ? $content : ['content' => $content]);
                $email->html($html);
            } else {
                $email->text(is_array($content) ? json_encode($content) : $content);
            }

            $this->mailer->send($email);
            
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email: ' . $e->getMessage(), [
                'recipient' => $recipient,
                'subject' => $subject,
                'exception' => $e,
            ]);
            
            return false;
        }
    }
}
```

#### SmsNotifier

```php
<?php

declare(strict_types=1);

namespace App\Service\Notifier;

use App\Interface\NotifierInterface;
use Psr\Log\LoggerInterface;

final readonly class SmsNotifier implements NotifierInterface
{
    public function __construct(
        private LoggerInterface $logger,
        // Injecter un service SMS ici
    ) {
    }

    public function send(string $recipient, string $subject, string|array $content, array $options = []): bool
    {
        try {
            // Logique d'envoi de SMS
            // ...

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de l\'envoi du SMS: ' . $e->getMessage(), [
                'recipient' => $recipient,
                'exception' => $e,
            ]);
            
            return false;
        }
    }
}
```

### 3. Factory pour les Notificateurs

```php
<?php

declare(strict_types=1);

namespace App\Service\Notifier;

use App\Interface\NotifierInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class NotifierFactory
{
    /**
     * @param iterable<NotifierInterface> $notifiers
     */
    public function __construct(
        #[TaggedIterator('app.notifier')]
        private readonly iterable $notifiers
    ) {
    }

    public function create(string $type): NotifierInterface
    {
        foreach ($this->notifiers as $notifier) {
            if ($this->getNotifierType($notifier) === $type) {
                return $notifier;
            }
        }
        
        throw new \InvalidArgumentException(sprintf('Notificateur "%s" non trouvé', $type));
    }

    private function getNotifierType(NotifierInterface $notifier): string
    {
        $className = get_class($notifier);
        $shortName = (new \ReflectionClass($className))->getShortName();
        return strtolower(str_replace('Notifier', '', $shortName));
    }
}
```

### 4. Service NotificationManager

```php
<?php

declare(strict_types=1);

namespace App\Service\Notifier;

use App\Interface\NotifierInterface;
use Psr\Log\LoggerInterface;

readonly class NotificationManager
{
    public function __construct(
        private NotifierFactory $notifierFactory,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Envoie une notification via le canal spécifié
     */
    public function notify(string $channel, string $recipient, string $subject, string|array $content, array $options = []): bool
    {
        try {
            $notifier = $this->notifierFactory->create($channel);
            return $notifier->send($recipient, $subject, $content, $options);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de la notification: ' . $e->getMessage(), [
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'exception' => $e,
            ]);
            
            return false;
        }
    }

    /**
     * Envoie une notification via plusieurs canaux
     * 
     * @param array<string> $channels
     * @return array<string, bool>
     */
    public function notifyMultiChannel(array $channels, string $recipient, string $subject, string|array $content, array $options = []): array
    {
        $results = [];
        
        foreach ($channels as $channel) {
            $results[$channel] = $this->notify($channel, $recipient, $subject, $content, $options);
        }
        
        return $results;
    }
}
```

### 5. Configuration des Services

```yaml
# config/services.yaml
services:
    # ...
    
    # Tagging automatique des notificateurs
    _instanceof:
        App\Interface\NotifierInterface:
            tags: ['app.notifier']
    
    # Configuration spécifique des notifiers si nécessaire
    App\Service\Notifier\EmailNotifier:
        arguments:
            $mailerFrom: '%env(MAILER_FROM)%'
```

## Utilisation

### Exemple dans un Controller

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Notifier\NotificationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    #[Route('/send-notification', name: 'send_notification')]
    public function sendNotification(NotificationManager $notificationManager): Response
    {
        // Envoi d'un email
        $notificationManager->notify(
            'email',
            'user@example.com',
            'Bienvenue sur notre plateforme',
            [
                'username' => 'JohnDoe',
                'activationLink' => 'https://example.com/activate/token123'
            ],
            ['template' => 'emails/welcome.html.twig']
        );
        
        // Envoi via plusieurs canaux
        $results = $notificationManager->notifyMultiChannel(
            ['email', 'sms'],
            'user@example.com', // ou numéro de téléphone
            'Alerte de sécurité',
            'Une connexion inhabituelle a été détectée sur votre compte.',
            []
        );
        
        return $this->json(['success' => true, 'results' => $results]);
    }
}
```

## Étendre le Système

Pour ajouter un nouveau canal de notification, créez simplement une nouvelle classe implémentant `NotifierInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Notifier;

use App\Interface\NotifierInterface;
use Psr\Log\LoggerInterface;

final readonly class PushNotifier implements NotifierInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private PushService $pushService
    ) {
    }

    public function send(string $recipient, string $subject, string|array $content, array $options = []): bool
    {
        try {
            // Logique d'envoi de notification push
            $this->pushService->sendPush(
                $recipient, // token du device
                $subject,
                is_array($content) ? $content['message'] ?? '' : $content,
                $options['data'] ?? []
            );
            
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de l\'envoi de la notification push: ' . $e->getMessage(), [
                'recipient' => $recipient,
                'subject' => $subject,
                'exception' => $e,
            ]);
            
            return false;
        }
    }
}
```

