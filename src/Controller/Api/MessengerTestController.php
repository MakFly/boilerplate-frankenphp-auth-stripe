<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Message\TestNotificationMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/test', name: 'api_test_')]
final class MessengerTestController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
    }

    #[Route('/send-notification', name: 'send_notification', methods: ['POST'])]
    public function sendNotification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $content = $data['content'] ?? 'Message de test à ' . (new \DateTimeImmutable())->format('H:i:s');

        $message = new TestNotificationMessage($content);
        $this->messageBus->dispatch($message);

        return $this->json([
            'status' => 'success',
            'message' => 'Notification envoyée avec succès',
            'content' => $content,
            'sent_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], Response::HTTP_CREATED);
    }

    #[Route('/send-multiple-notifications/{count}', name: 'send_multiple_notifications', methods: ['POST'])]
    public function sendMultipleNotifications(int $count = 5): JsonResponse
    {
        $sentMessages = [];

        for ($i = 1; $i <= $count; $i++) {
            $content = "Message de test #$i envoyé à " . (new \DateTimeImmutable())->format('H:i:s');
            $message = new TestNotificationMessage($content);
            $this->messageBus->dispatch($message);
            $sentMessages[] = $content;
        }

        return $this->json([
            'status' => 'success',
            'message' => "$count notifications envoyées avec succès",
            'sent_messages' => $sentMessages,
            'sent_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], Response::HTTP_CREATED);
    }
}