<?php

namespace App\Repository;

use App\Entity\Bundle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BundleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bundle::class);
    }

    public function findActiveBundles(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}