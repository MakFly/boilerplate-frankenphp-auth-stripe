<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\DTO\Response\ApiResponse;
use App\Enum\ApiMessage;
use App\Enum\ResetPasswordEnum;
use App\Service\Auth\ResetPasswordService;
use App\Service\JsonRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly ResetPasswordService $resetPasswordService,
    ) {
    }

    #[Route('/reset-password/request', name: 'app_request_reset_password', methods: ['POST'])]
    public function requestReset(Request $request): JsonResponse
    {
        $data = JsonRequestService::getJsonRequest($request);
        $email = $data['email'] ?? null;

        if (!$email) {
            return ApiResponse::badRequest(ApiMessage::INVALID_DATA->value);
        }

        $result = $this->resetPasswordService->requestPasswordReset($email);

        if (!$result) {
            return ApiResponse::badRequest(ResetPasswordEnum::TOKEN_ALREADY_SENT->value);
        }

        return ApiResponse::success(
            ResetPasswordEnum::RESET_PASSWORD_REQUEST_SENT->value
        );
    }

    #[Route('/reset-password/check/{token}', name: 'app_check_reset_password', methods: ['GET'])]
    public function check(string $token): JsonResponse
    {
        $result = $this->resetPasswordService->checkToken($token);

        if (!$result) {
            return ApiResponse::badRequest(ResetPasswordEnum::INVALID_TOKEN->value);
        }

        return ApiResponse::success([
            'isValid' => $result,
        ]);
    }

    #[Route('/reset-password/reset/{token}', name: 'app_reset_password', methods: ['POST'])]
    public function reset(Request $request, string $token): JsonResponse
    {
        $data = JsonRequestService::getJsonRequest($request);
        $password = $data['password'] ?? null;

        if (!$password) {
            return ApiResponse::badRequest(ApiMessage::INVALID_DATA->value);
        }

        $result = $this->resetPasswordService->resetPassword($token, $password);

        if (!$result) {
            return ApiResponse::badRequest(ResetPasswordEnum::INVALID_TOKEN->value);
        }

        return ApiResponse::success(
            ResetPasswordEnum::RESET_PASSWORD_SUCCESS->value
        );
    }
} 
