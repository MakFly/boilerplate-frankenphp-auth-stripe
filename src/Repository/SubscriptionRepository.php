<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 *
 * @method Subscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method Subscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method Subscription[]    findAll()
 * @method Subscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly UserRepository $userRepository
    ) {
        parent::__construct($registry, Subscription::class);
    }

    public function save(Subscription $subscription, bool $flush = true): void
    {
        $this->getEntityManager()->persist($subscription);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByStripeId(string $stripeId): ?Subscription
    {
        return $this->findOneBy(['stripeId' => $stripeId]);
    }
    
    /**
     * Trouve un utilisateur par son ID client Stripe.
     */
    public function findUserByStripeCustomerId(string $stripeCustomerId): ?User
    {
        return $this->userRepository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);
    }
    
    /**
     * Trouve l'abonnement actif pour un utilisateur donné.
     */
    public function findActiveSubscriptionByUser(User $user): ?Subscription
    {
        return $this->findOneBy([
            'user' => $user,
            'status' => 'active'
        ]);
    }

    /**
     * Trouve un abonnement par son ID d'abonnement Stripe
     */
    public function findOneByStripeSubscriptionId(string $stripeSubscriptionId): ?Subscription
    {
        return $this->findOneBy(['stripeSubscriptionId' => $stripeSubscriptionId]);
    }
    
    /**
     * Trouve le dernier abonnement créé pour un utilisateur donné
     */
    public function findLatestSubscriptionByUser(User $user): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les abonnements en attente (sans stripeSubscriptionId)
     */
    public function findPendingSubscriptions(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.stripeSubscriptionId IS NULL')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Trouve tous les abonnements en attente créés avant une date donnée
     * 
     * @param \DateTimeInterface $date La date limite
     * @return array<Subscription> Liste des abonnements en attente
     */
    public function findPendingBeforeDate(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.createdAt < :date')
            ->setParameter('status', 'pending')
            ->setParameter('date', $date)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}