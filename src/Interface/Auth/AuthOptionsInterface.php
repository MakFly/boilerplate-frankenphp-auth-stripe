<?php

declare(strict_types=1);

namespace App\Interface\Auth;

interface AuthOptionsInterface
{
    /**
     * Check if OTP is enabled for current request
     *
     * @return bool
     */
    public function isOTPEnabled(): bool;

    /**
     * Get current request IP address
     *
     * @return string|null
     */
    public function getCurrentIpAddress(): ?string;
}