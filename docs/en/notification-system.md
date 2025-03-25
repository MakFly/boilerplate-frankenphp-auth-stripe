# Documentation: Generic Notification System for Symfony

## Introduction

This document presents the implementation of a generic notification system for Symfony, based on the Strategy pattern, Decorator pattern, and Factory pattern. This architecture allows sending notifications easily through different channels (email, SMS, push notifications, etc.) while maintaining a coherent and extensible interface.

## System Architecture

The system is structured around several key components:

1. **NotifierInterface**: Defines the contract for all notification services
2. **Specific Notifiers**: Implementations for each notification channel
3. **NotifierFactory**: Creates appropriate notifier instances
4. **NotificationManager**: Facade that coordinates notification sending

### Class Diagram

```
NotifierInterface
       ↑
       |
       ├─── EmailNotifier
       ├─── SmsNotifier
       └─── [Other notifiers]
       
NotifierFactory ────► NotificationManager
```

## Implementation

### 1. NotifierInterface

```php
<?php

declare(strict_types=1);

namespace App\Interface;

interface NotifierInterface
{
    /**
     * Sends a notification
     * 
     * @param string $recipient The notification recipient
     * @param string $subject The notification subject
     * @param string|array $content The notification content
     * @param array<string, mixed> $options Channel-specific options
     * 
     * @return bool Success or failure of sending
     */
    public function send(string $recipient, string $subject, string|array $content, array $options = []): bool;
}
```

### 2. Notifier Implementations

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

            // Extract template from options
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
            $this->logger->error('Error sending email: ' . $e->getMessage(), [
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
        // Inject SMS service here
    ) {
    }

    public function send(string $recipient, string $subject, string|array $content, array $options = []): bool
    {
        try {
            // SMS sending logic
            // ...

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Error sending SMS: ' . $e->getMessage(), [
                'recipient' => $recipient,
                'exception' => $e,
            ]);
            
            return false;
        }
    }
}
```

### 3. Notifier Factory

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
        
        throw new \InvalidArgumentException(sprintf('Notifier "%s" not found', $type));
    }

    private function getNotifierType(NotifierInterface $notifier): string
    {
        $className = get_class($notifier);
        $shortName = (new \ReflectionClass($className))->getShortName();
        return strtolower(str_replace('Notifier', '', $shortName));
    }
}
```

### 4. NotificationManager Service

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
     * Sends a notification through the specified channel
     */
    public function notify(string $channel, string $recipient, string $subject, string|array $content, array $options = []): bool
    {
        try {
            $notifier = $this->notifierFactory->create($channel);
            return $notifier->send($recipient, $subject, $content, $options);
        } catch (\Throwable $e) {
            $this->logger->error('Error during notification: ' . $e->getMessage(), [
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'exception' => $e,
            ]);
            
            return false;
        }
    }

    /**
     * Sends a notification through multiple channels
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

### 5. Service Configuration

```yaml
# config/services.yaml
services:
    # ...
    
    # Automatic tagging of notifiers
    _instanceof:
        App\Interface\NotifierInterface:
            tags: ['app.notifier']
    
    # Specific notifier configuration if needed
    App\Service\Notifier\EmailNotifier:
        arguments:
            $mailerFrom: '%env(MAILER_FROM)%'
```

## Usage

### Example in Controller

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
        // Send an email
        $notificationManager->notify(
            'email',
            'user@example.com',
            'Welcome to our platform',
            [
                'username' => 'JohnDoe',
                'activationLink' => 'https://example.com/activate/token123'
            ],
            ['template' => 'emails/welcome.html.twig']
        );
        
        // Send via multiple channels
        $results = $notificationManager->notifyMultiChannel(
            ['email', 'sms'],
            'user@example.com', // or phone number
            'Security Alert',
            'Unusual login detected on your account.',
            []
        );
        
        return $this->json(['success' => true, 'results' => $results]);
    }
}
```

## Extending the System

To add a new notification channel, simply create a new class implementing `NotifierInterface`:

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
            // Push notification sending logic
            $this->pushService->sendPush(
                $recipient, // device token
                $subject,
                is_array($content) ? $content['message'] ?? '' : $content,
                $options['data'] ?? []
            );
            
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Error sending push notification: ' . $e->getMessage(), [
                'recipient' => $recipient,
                'subject' => $subject,
                'exception' => $e,
            ]);
            
            return false;
        }
    }
}
```