<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendNotificationMessage
{
    /**
     * @param string $channel
     * @param string $recipient
     * @param string $subject
     * @param string|array<string, mixed> $content
     * @param array<string, mixed> $options
     */
    public function __construct(
        private string $channel,
        private string $recipient,
        private string $subject,
        private string|array $content,
        private array $options = []
    ) {
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return string|array<string, mixed>
     */
    public function getContent(): string|array
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}