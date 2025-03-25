<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Repository\UserRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class AdminService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Récupère les statistiques globales pour le dashboard admin
     */
    public function getDashboardStats(): array
    {
        $totalUsers = $this->userRepository->count([]);
        $adminUsers = $this->userRepository->count(['roles' => 'ROLE_ADMIN']);
        $recentUsers = $this->userRepository->findBy([], ['createdAt' => 'DESC'], 5);

        return [
            'total_users' => $totalUsers,
            'admin_users' => $adminUsers,
            'recent_users' => $recentUsers,
        ];
    }

    /**
     * Vérifie si un utilisateur a des accès administrateur
     */
    public function hasAdminAccess(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /**
     * Recherche des utilisateurs avec filtres
     * 
     * @param array<string, string> $criteria Les critères de recherche
     * @return array<int, User> La liste des utilisateurs correspondants
     */
    public function searchUsers(array $criteria): array
    {
        $qb = $this->userRepository->createQueryBuilder('u');

        if (isset($criteria['role'])) {
            $qb->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
               ->setParameter('role', json_encode($criteria['role']));
        }

        if (isset($criteria['search'])) {
            $qb->andWhere('u.email LIKE :search OR u.username LIKE :search')
               ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        return $qb->getQuery()->getResult();
    }
}