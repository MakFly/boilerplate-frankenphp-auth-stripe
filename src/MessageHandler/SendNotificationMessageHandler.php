<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendNotificationMessage;
use App\Service\Notifier\NotificationManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'notifications')]
final readonly class SendNotificationMessageHandler
{
    public function __construct(
        private NotificationManager $notificationManager,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $this->logger->info('Traitement asynchrone de notification', [
            'channel' => $message->getChannel(),
            'recipient' => $message->getRecipient()
        ]);

        $this->notificationManager->notify(
            $message->getChannel(),
            $message->getRecipient(),
            $message->getSubject(),
            $message->getContent(),
            $message->getOptions()
        );
    }
}