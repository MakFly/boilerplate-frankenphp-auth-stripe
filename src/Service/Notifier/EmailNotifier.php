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