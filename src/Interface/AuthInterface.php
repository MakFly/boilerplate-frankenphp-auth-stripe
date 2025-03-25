<?php

declare(strict_types=1);

namespace App\Interface;

use App\DTO\Auth\LoginRequest;

interface AuthInterface
{
    public function authCustom(LoginRequest $data): array|bool;
    public function verifyOtp(string $email, int $otp): array;
    public function register(string $email, string $password, string $username): array;
    public function verifyJwt(string $token): array;
}
