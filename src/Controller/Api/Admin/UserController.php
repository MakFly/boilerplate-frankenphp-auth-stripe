<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\DTO\Response\ApiResponse;
use App\Service\Admin\AdminService;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin/users', name: 'api_admin_users_')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly AdminService $adminService,
        private readonly UserRepository $userRepository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $criteria = [
            'role' => $request->query->get('role'),
            'search' => $request->query->get('search'),
        ];

        $users = $this->adminService->searchUsers($criteria);
        
        return ApiResponse::success([
            'users' => $this->serializer->serialize($users, 'json', ['groups' => ['user:read']])
        ]);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): Response
    {
        return ApiResponse::success(
            $this->adminService->getDashboardStats()
        );
    }

    #[Route('/{id}/promote', name: 'promote', methods: ['POST'])]
    public function promote(string $id): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return ApiResponse::notFound();
        }

        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles)) {
            $user->setRoles(array_merge($roles, ['ROLE_ADMIN']));
            $this->userRepository->save($user, true);
        }

        return ApiResponse::success(['message' => 'User promoted successfully']);
    }

    #[Route('/{id}/demote', name: 'demote', methods: ['POST'])]
    public function demote(string $id): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return ApiResponse::notFound();
        }

        $roles = array_diff($user->getRoles(), ['ROLE_ADMIN']);
        $user->setRoles($roles);
        $this->userRepository->save($user, true);

        return ApiResponse::success(['message' => 'User demoted successfully']);
    }
}