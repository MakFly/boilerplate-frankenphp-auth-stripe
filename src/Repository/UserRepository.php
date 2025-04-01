<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserJit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 * @implements PasswordUpgraderInterface<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry, private EntityManagerInterface $em)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->em->persist($user);
        $this->em->flush();
    }

    public function save(User $user, bool $flush = false): void
    {
        $this->em->persist($user);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', json_encode($role))
            ->getQuery()
            ->getResult();
    }

    public function getActiveUsersCount(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.lastLogin > :date')
            ->setParameter('date', new \DateTime('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve un utilisateur par son ID client Stripe
     */
    public function findOneByStripeCustomerId(string $stripeCustomerId): ?User
    {
        return $this->findOneBy(['stripeCustomerId' => $stripeCustomerId]);
    }

    /**
     * @param User $user
     * @return UserJit|null
     */
    public function getUserJit(User $user): ?UserJit
    {
        return $this->em->getRepository(UserJit::class)->findOneBy(['user' => $user]);
    }

    /**
     * @param User $user
     * @param string $jwtId
     * @return void
     */
    public function updateUserJit(User $user, string $jwtId): void
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        $qb = $this->em->createQueryBuilder();

        $qb->update(UserJit::class, 'uj')
            ->set('uj.jwtId', ':jwtId')
            ->set('uj.updatedAt', ':date')
            ->where('uj.user = :user')
            ->setParameter('jwtId', $jwtId)
            ->setParameter('user', $user)
            ->setParameter('date', $date);

        $qb->getQuery()->execute();
    }
}
