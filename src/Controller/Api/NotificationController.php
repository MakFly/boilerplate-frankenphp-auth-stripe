<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Notifier\NotificationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/notifications', name: 'api_notifications_')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationManager $notificationManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/send', name: 'send', methods: ['POST'])]
    public function sendNotification(Request $request): JsonResponse
    {
        // Récupérer et valider les données
        $data = json_decode($request->getContent(), true);

        $constraints = new Assert\Collection([
            'channel' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
            'recipient' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
            'subject' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
            'content' => new Assert\Required([new Assert\NotBlank()]),
            'options' => new Assert\Optional([new Assert\Type('array')]),
            'async' => new Assert\Optional([new Assert\Type('boolean')])
        ]);

        $errors = $this->validator->validate($data, $constraints);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['success' => false, 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si la demande est asynchrone
        $isAsync = $data['async'] ?? false;
        $channel = $data['channel'];
        $recipient = $data['recipient'];
        $subject = $data['subject'];
        $content = $data['content'];
        $options = $data['options'] ?? [];

        // Envoyer la notification (synchrone ou asynchrone)
        if ($isAsync) {
            $this->notificationManager->notifyAsync($channel, $recipient, $subject, $content, $options);
            return $this->json([
                'success' => true,
                'message' => 'Notification mise en file d\'attente pour envoi asynchrone'
            ]);
        } else {
            $result = $this->notificationManager->notify($channel, $recipient, $subject, $content, $options);
            return $this->json([
                'success' => $result,
                'message' => $result ? 'Notification envoyée avec succès' : 'Échec de l\'envoi de la notification'
            ]);
        }
    }

    #[Route('/send-multi', name: 'send_multi', methods: ['POST'])]
    public function sendMultiChannelNotification(Request $request): JsonResponse
    {
        // Récupérer et valider les données
        $data = json_decode($request->getContent(), true);

        $constraints = new Assert\Collection([
            'channels' => new Assert\Required([
                new Assert\NotBlank(), 
                new Assert\Type('array'), 
                new Assert\Count(['min' => 1]),
                new Assert\All([new Assert\Type('string')])
            ]),
            'recipient' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
            'subject' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
            'content' => new Assert\Required([new Assert\NotBlank()]),
            'options' => new Assert\Optional([new Assert\Type('array')]),
            'async' => new Assert\Optional([new Assert\Type('boolean')])
        ]);

        $errors = $this->validator->validate($data, $constraints);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['success' => false, 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si la demande est asynchrone
        $isAsync = $data['async'] ?? false;
        $channels = $data['channels'];
        $recipient = $data['recipient'];
        $subject = $data['subject'];
        $content = $data['content'];
        $options = $data['options'] ?? [];

        // Envoyer les notifications multi-canal (synchrone ou asynchrone)
        if ($isAsync) {
            $this->notificationManager->notifyMultiChannelAsync($channels, $recipient, $subject, $content, $options);
            return $this->json([
                'success' => true,
                'message' => 'Notifications mises en file d\'attente pour envoi asynchrone'
            ]);
        } else {
            $results = $this->notificationManager->notifyMultiChannel($channels, $recipient, $subject, $content, $options);
            return $this->json([
                'success' => in_array(true, $results, true),
                'results' => $results
            ]);
        }
    }
}