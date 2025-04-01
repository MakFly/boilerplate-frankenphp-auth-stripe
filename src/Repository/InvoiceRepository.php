<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 *
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Invoice[]    findAll()
 * @method Invoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $invoice): void
    {
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
    }
    
    public function findByPayment(Payment $payment): ?Invoice
    {
        return $this->findOneBy(['payment' => $payment]);
    }
    
    public function findBySubscription(Subscription $subscription): ?Invoice
    {
        return $this->findOneBy(['subscription' => $subscription]);
    }
    
    public function findByStripeInvoiceId(string $stripeInvoiceId): ?Invoice
    {
        return $this->findOneBy(['stripeInvoiceId' => $stripeInvoiceId]);
    }

    public function findOneByStripeInvoiceId(string $stripeInvoiceId): ?Invoice
    {
        return $this->findByStripeInvoiceId($stripeInvoiceId);
    }
}