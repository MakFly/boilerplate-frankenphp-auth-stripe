<?php

declare(strict_types=1);

namespace App\EventListener;

use App\DTO\Response\ApiResponse;
use App\Enum\ApiMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ExceptionListener
{
    public function __construct(
        private readonly string $environment,
        private readonly LoggerInterface $logger
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        if ('dev' === $this->environment && !str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $previous = $exception->getPrevious();

        $this->logger->debug('Exception caught:', [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);

        if ($exception instanceof ValidationFailedException || $previous instanceof ValidationFailedException) {
            $response = new ApiResponse(
                null,
                ApiMessage::INVALID_DATA->value,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
            $event->setResponse($response);
            return;
        }

        if ($exception instanceof AccessDeniedException) {
            $response = ApiResponse::forbidden(
                ApiMessage::FORBIDDEN->value
            );
            $event->setResponse($response);
            return;
        }

        if ($exception instanceof AuthenticationException) {
            $response = new ApiResponse(
                null,
                $exception->getMessage(),
                Response::HTTP_UNAUTHORIZED
            );
            $event->setResponse($response);
            return;
        }

        if ($exception instanceof HttpException) {
            $message = match ($exception->getStatusCode()) {
                Response::HTTP_NOT_FOUND => ApiMessage::RESOURCE_NOT_FOUND->value,
                Response::HTTP_FORBIDDEN => ApiMessage::FORBIDDEN->value,
                Response::HTTP_UNPROCESSABLE_ENTITY => ApiMessage::INVALID_DATA->value,
                Response::HTTP_BAD_REQUEST => ApiMessage::INVALID_DATA->value,
                default => ApiMessage::INVALID_DATA->value
            };
            
            $statusCode = $exception->getStatusCode();
            if ($statusCode === Response::HTTP_BAD_REQUEST && 
                ($exception->getMessage() === ApiMessage::INVALID_DATA->value || 
                 $exception->getMessage() === ApiMessage::MISSING_FIELDS->value)) {
                $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
            
            $response = new ApiResponse(
                null,
                $message,
                $statusCode
            );
            $event->setResponse($response);
            return;
        }

        if ($exception instanceof NotFoundHttpException) {
            $response = ApiResponse::notFound(
                ApiMessage::RESOURCE_NOT_FOUND->value
            );
        } else {
            $this->logger->error('Unhandled exception occurred', [
                'exception' => $exception,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);
            $response = new ApiResponse(
                null,
                ApiMessage::INTERNAL_SERVER_ERROR->value,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        
        $event->setResponse($response);
    }
}