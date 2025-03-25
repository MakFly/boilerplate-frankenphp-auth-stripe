<?php
declare(strict_types=1);
namespace App\Exception\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
final class AuthenticationFailedException extends HttpException
{
    /**
     * @param array<string, string|array<string>> $headers Les en-têtes HTTP
     */
    public function __construct(
        string $message = 'Échec de l\'authentification',
        ?\Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            $message,
            $previous,
            $headers,
            $code
        );
    }
}