<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Repository\UserRepository;
use App\Entity\User;

final class AdminService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Retrieves global statistics for admin dashboard
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
     * Checks if user has admin access
     */
    public function hasAdminAccess(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /**
     * Searches users with filters
     * 
     * @param array<string, string> $criteria Search criteria
     * @return array<int, User> List of matching users
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

    /**
     * Retrieves all users
     */
    public function getAllUsers(): array
    {
        return $this->userRepository->findAll();
    }

    /**
     * Finds user by ID
     */
    public function findUserById(string $id): ?User
    {
        return $this->userRepository->find($id);
    }

    /**
     * Promotes user to admin role
     */
    public function promoteUserToAdmin(string $userId): bool
    {
        $user = $this->findUserById($userId);
        
        if (!$user) {
            return false;
        }

        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles)) {
            $user->setRoles(array_merge($roles, ['ROLE_ADMIN']));
            $this->userRepository->save($user, true);
        }

        return true;
    }

    /**
     * Removes admin role from user
     */
    public function demoteUserFromAdmin(string $userId): bool
    {
        $user = $this->findUserById($userId);
        
        if (!$user) {
            return false;
        }

        $roles = array_diff($user->getRoles(), ['ROLE_ADMIN']);
        $user->setRoles($roles);
        $this->userRepository->save($user, true);

        return true;
    }
}