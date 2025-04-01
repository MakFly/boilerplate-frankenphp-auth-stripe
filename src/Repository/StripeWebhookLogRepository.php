<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StripeWebhookLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StripeWebhookLog>
 *
 * @method StripeWebhookLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method StripeWebhookLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method StripeWebhookLog[]    findAll()
 * @method StripeWebhookLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StripeWebhookLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StripeWebhookLog::class);
    }

    public function save(StripeWebhookLog $webhookLog, bool $flush = true): void
    {
        $this->getEntityManager()->persist($webhookLog);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve un webhook par son ID d'événement Stripe
     */
    public function findByEventId(string $eventId): ?StripeWebhookLog
    {
        return $this->findOneBy(['eventId' => $eventId]);
    }

    /**
     * Trouve tous les webhooks en erreur
     */
    public function findErrors(int $limit = 50): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.status = :status')
            ->setParameter('status', StripeWebhookLog::STATUS_ERROR)
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les webhooks non traités
     */
    public function findPending(int $limit = 50): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.status = :status')
            ->setParameter('status', StripeWebhookLog::STATUS_PROCESSING)
            ->orderBy('w.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les statistiques des webhooks
     */
    public function getStats(): array
    {
        $qb = $this->createQueryBuilder('w')
            ->select('w.status, COUNT(w.id) as count')
            ->groupBy('w.status');

        $result = $qb->getQuery()->getResult();
        
        $stats = [];
        foreach ($result as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }
        
        // Assurez-vous que tous les statuts sont présents
        foreach ([
            StripeWebhookLog::STATUS_SUCCESS,
            StripeWebhookLog::STATUS_ERROR,
            StripeWebhookLog::STATUS_PROCESSING,
            StripeWebhookLog::STATUS_IGNORED
        ] as $status) {
            if (!isset($stats[$status])) {
                $stats[$status] = 0;
            }
        }
        
        return $stats;
    }
} 