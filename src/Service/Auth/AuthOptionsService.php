<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\HttpFoundation\RequestStack;

abstract class AuthOptionsService
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    public function isOTPEnabled(): bool
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        return $currentRequest?->attributes->get('account_otp_enabled');
    }
}