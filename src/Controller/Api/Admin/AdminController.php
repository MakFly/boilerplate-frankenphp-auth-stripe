<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\DTO\Response\ApiResponse;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin', name: 'api_admin_')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/users', name: 'list_users', methods: ['GET'])]
    public function listUsers(): Response
    {
        $users = $this->userRepository->findAll();
        
        return ApiResponse::success([
            'users' => $this->serializer->serialize($users, 'json', ['groups' => ['user:read']])
        ]);
    }

    #[Route('/users/{id}/promote', name: 'promote_user', methods: ['POST'])]
    public function promoteUser(string $id): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return ApiResponse::notFound();
        }

        // Ajout du rôle admin
        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles)) {
            $user->setRoles(array_merge($roles, ['ROLE_ADMIN']));
            $this->userRepository->save($user, true);
        }

        return ApiResponse::success(['message' => 'User promoted successfully']);
    }

    #[Route('/users/{id}/demote', name: 'demote_user', methods: ['POST'])]
    public function demoteUser(string $id): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return ApiResponse::notFound();
        }

        // Suppression du rôle admin
        $roles = array_diff($user->getRoles(), ['ROLE_ADMIN']);
        $user->setRoles($roles);
        $this->userRepository->save($user, true);

        return ApiResponse::success(['message' => 'User demoted successfully']);
    }
}