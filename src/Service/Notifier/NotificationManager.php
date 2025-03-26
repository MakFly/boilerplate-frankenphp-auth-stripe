<?php

declare(strict_types=1);

namespace App\Service\Notifier;

use App\Message\SendNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class NotificationManager
{
    public function __construct(
        private NotifierFactory $notifierFactory,
        private LoggerInterface $logger,
        private ?MessageBusInterface $messageBus = null
    ) {
    }

    /**
     * Envoie une notification de façon synchrone via le canal spécifié
     * 
     * @param string $channel
     * @param string $recipient
     * @param string $subject
     * @param string|array<string, mixed> $content
     * @param array<string, mixed> $options
     * @return bool
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
     * @param string $recipient
     * @param string $subject
     * @param string|array<string, mixed> $content
     * @param array<string, mixed> $options
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
    
    /**
     * Envoie une notification de façon asynchrone via le canal spécifié
     * Cette méthode place le message dans une file d'attente pour traitement ultérieur
     * 
     * @param string $channel
     * @param string $recipient
     * @param string $subject
     * @param string|array<string, mixed> $content
     * @param array<string, mixed> $options
     */
    public function notifyAsync(string $channel, string $recipient, string $subject, string|array $content, array $options = []): void
    {
        if ($this->messageBus === null) {
            throw new \LogicException('Le MessageBus n\'est pas configuré. Impossible d\'envoyer une notification asynchrone.');
        }
        
        $message = new SendNotificationMessage($channel, $recipient, $subject, $content, $options);
        $this->messageBus->dispatch($message);
        
        $this->logger->info('Notification mise en file d\'attente', [
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject
        ]);
    }
    
    /**
     * Envoie une notification via plusieurs canaux de façon asynchrone
     * 
     * @param array<string> $channels
     * @param string $recipient
     * @param string $subject
     * @param string|array<string, mixed> $content
     * @param array<string, mixed> $options
     */
    public function notifyMultiChannelAsync(array $channels, string $recipient, string $subject, string|array $content, array $options = []): void
    {
        foreach ($channels as $channel) {
            $this->notifyAsync($channel, $recipient, $subject, $content, $options);
        }
    }
}