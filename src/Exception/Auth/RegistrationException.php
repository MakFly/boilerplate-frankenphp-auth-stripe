<?php

declare(strict_types=1);

namespace App\Exception\Auth;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegistrationException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, $message, $previous);
    }
}