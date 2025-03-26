<?php

declare(strict_types=1);

namespace App\Message;

final class TestNotificationMessage
{
    public function __construct(
        private readonly string $content,
        private readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable()
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}