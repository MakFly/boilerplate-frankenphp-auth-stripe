<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StripeProducts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StripeProducts>
 *
 * @method StripeProducts|null find($id, $lockMode = null, $lockVersion = null)
 * @method StripeProducts|null findOneBy(array $criteria, array $orderBy = null)
 * @method StripeProducts[]    findAll()
 * @method StripeProducts[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StripeProductsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StripeProducts::class);
    }

    public function findOneByPlanId(string $planId): ?StripeProducts
    {
        return $this->findOneBy(['planId' => $planId]);
    }
}