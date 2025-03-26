<?php

declare(strict_types=1);

namespace App\Service\Notifier;

use App\Interface\NotifierInterface;
use Psr\Log\LoggerInterface;

final readonly class SmsNotifier implements NotifierInterface
{
    public function __construct(
        private LoggerInterface $logger
        // Dans un cas réel, on injecterait ici un service SMS
        // private SmsServiceInterface $smsService
    ) {
    }

    public function send(string $recipient, string $subject, string|array $content, array $options = []): bool
    {
        try {
            // Simulation d'envoi de SMS pour l'exemple
            $this->logger->info('SMS would be sent', [
                'to' => $recipient,
                'subject' => $subject,
                'content' => is_array($content) ? json_encode($content) : $content
            ]);
            
            // Dans un cas réel:
            // $this->smsService->sendMessage($recipient, $content);
            
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