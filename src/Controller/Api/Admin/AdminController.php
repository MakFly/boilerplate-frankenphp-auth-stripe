<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\DTO\Response\ApiResponse;
use App\Service\Admin\AdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin', name: 'api_admin_')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly AdminService $adminService,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/users', name: 'list_users', methods: ['GET'])]
    public function listUsers(): Response
    {
        $users = $this->adminService->getAllUsers();
        
        return ApiResponse::success([
            'users' => $this->serializer->serialize($users, 'json', ['groups' => ['user:read']])
        ]);
    }

    #[Route('/users/{id}/promote', name: 'promote_user', methods: ['POST'])]
    public function promoteUser(string $id): Response
    {
        $success = $this->adminService->promoteUserToAdmin($id);
        
        if (!$success) {
            return ApiResponse::notFound();
        }

        return ApiResponse::success(['message' => 'User promoted successfully']);
    }

    #[Route('/users/{id}/demote', name: 'demote_user', methods: ['POST'])]
    public function demoteUser(string $id): Response
    {
        $success = $this->adminService->demoteUserFromAdmin($id);
        
        if (!$success) {
            return ApiResponse::notFound();
        }

        return ApiResponse::success(['message' => 'User demoted successfully']);
    }
}