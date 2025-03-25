<?php

declare(strict_types=1);

namespace App\Service\Notifier;

use App\Interface\NotifierInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class EmailNotifier implements NotifierInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        #[Autowire('%mailer_from%')]
        private readonly string $mailerFrom,
        private readonly LoggerInterface $logger
    ) {
    }

    public function send(string $to, string $subject, array $content, string $template): void
    {
        try {
            $email = new Email();
            $email->from($this->mailerFrom)
                  ->to($to)
                  ->subject($subject);
            
            $html = $this->twig->render($template, $content);
            $email->html($html);
            
            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Email sending failed: ' . $e->getMessage());
            throw $e;
        }
    }
}