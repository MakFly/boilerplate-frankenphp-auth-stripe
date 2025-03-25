<?php

declare(strict_types=1);

namespace App\DTO\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Enum\ApiMessage;

/**
 * API Response formatter
 * 
 * Provides standardized JSON responses for API endpoints
 */
final class ApiResponse extends JsonResponse
{
    /**
     * Creates a new API response with standardized format
     *
     * @param mixed $data The response data
     * @param string|null $message Optional message to include in response
     * @param int $statusCode HTTP status code
     * @param array<string, string|array<string>> $headers HTTP headers
     * @param bool $json Whether data is already JSON encoded
     */
    public function __construct(
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200,
        array $headers = [],
        bool $json = false
    ) {
        $responseData = [
            'success' => $statusCode >= 200 && $statusCode < 300,
        ];

        if ($message !== null) {
            $responseData['message'] = $message;
        }

        // Si $data contient une clé 'items', on la remonte au niveau racine
        if (is_array($data) && isset($data['items'])) {
            $responseData = array_merge($responseData, $data);
        } else {
            $responseData['data'] = $data;
        }

        parent::__construct($responseData, $statusCode, $headers, $json);
    }

    /**
     * Creates a success response (HTTP 200) without specific message
     *
     * @param mixed $data The response data
     * @return self
     */
    public static function success(mixed $data = null): self
    {
        return new self($data, null, Response::HTTP_OK);
    }

    /**
     * Creates an error response with an enum message
     *
     * @param ApiMessage $message Error message enum
     * @param mixed $data Optional data to include
     * @param int $status HTTP status code
     * @return self
     */
    public static function error(ApiMessage $message, mixed $data = null, int $status = Response::HTTP_BAD_REQUEST): self
    {
        return new self($data, $message->value, $status);
    }

    /**
     * Creates a standard OK response (HTTP 200) with resource fetched message
     *
     * @param mixed $data The response data
     * @return self
     */
    public static function ok(mixed $data = null): self
    {
        return new self($data, ApiMessage::RESOURCE_FETCHED->value, Response::HTTP_OK);
    }

    /**
     * Creates a resource created response (HTTP 201)
     *
     * @param mixed $data The created resource data
     * @return self
     */
    public static function created(mixed $data = null): self
    {
        return new self($data, ApiMessage::RESOURCE_CREATED->value, Response::HTTP_CREATED);
    }

    /**
     * Creates a no content response (HTTP 204)
     * 
     * Note: According to HTTP specification, 204 responses should not include content
     *
     * @return self
     */
    public static function noContent(): self
    {
        return new self(null, null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Creates a not found response (HTTP 404)
     *
     * @param string $message Custom not found message
     * @return self
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return new self(null, $message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Creates a bad request response (HTTP 400)
     *
     * @param string $message Error message
     * @param mixed $errors Optional validation errors
     * @return self
     */
    public static function badRequest(string $message, mixed $errors = null): self
    {
        return new self($errors, $message, Response::HTTP_BAD_REQUEST);
    }

    /**
     * Creates a forbidden response (HTTP 403)
     *
     * @param string $message Custom forbidden message
     * @return self
     */
    public static function forbidden(string $message = 'Access denied'): self
    {
        return new self(null, $message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Creates an unprocessable entity response (HTTP 422)
     *
     * @param ApiMessage $message Error message enum
     * @param mixed $data Optional validation error details
     * @return self
     */
    public static function unprocessableEntity(ApiMessage $message, mixed $data = null): self
    {
        return new self($data, $message->value, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}