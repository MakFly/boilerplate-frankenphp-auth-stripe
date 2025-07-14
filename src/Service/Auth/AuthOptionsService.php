<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Interface\Auth\AuthOptionsInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class AuthOptionsService implements AuthOptionsInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    public function isOTPEnabled(): bool
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        return $currentRequest?->attributes->get('account_otp_enabled');
    }

    public function getCurrentIpAddress(): ?string
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        return $currentRequest?->getClientIp();
    }
}