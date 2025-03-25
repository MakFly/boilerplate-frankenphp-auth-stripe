<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_users_')]
final class UsersController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/users', name: 'get_all_users', methods: ['GET'])]
    public function index(): Response
    {
        $users = $this->userRepository->findAll();
        return $this->json($users, Response::HTTP_OK);
    }
}
