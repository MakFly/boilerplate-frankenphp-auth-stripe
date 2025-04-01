<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 *
 * @method Payment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Payment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Payment[]    findAll()
 * @method Payment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $payment): void
    {
        $this->getEntityManager()->persist($payment);
        $this->getEntityManager()->flush();
    }

    public function findOneByStripeId(string $stripeId): ?Payment
    {
        return $this->findOneBy(['stripeId' => $stripeId]);
    }

    public function findOneByPaymentIntentId(string $paymentIntentId): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.stripeId = :paymentIntentId OR p.paymentIntentId = :paymentIntentId')
            ->setParameter('paymentIntentId', $paymentIntentId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByCheckoutSessionId(string $sessionId): ?Payment
    {
        return $this->findOneBy(['stripeId' => $sessionId]);
    }
}