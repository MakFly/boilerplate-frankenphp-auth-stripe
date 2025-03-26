<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TestNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'notifications')]
final class TestNotificationMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(TestNotificationMessage $message): void
    {
        $content = $message->getContent();
        $createdAt = $message->getCreatedAt()->format('Y-m-d H:i:s');

        $this->logger->info('Message traité par le worker', [
            'content' => $content,
            'created_at' => $createdAt,
            'processed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Simuler un traitement qui prend du temps
        sleep(2);

        $this->logger->info('Traitement du message terminé', [
            'content' => $content,
        ]);
    }
}