<?php
// src/Repository/ExhibitorRepository.php

namespace App\Repository;

use App\Entity\Exhibitor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exhibitor>
 */
class ExhibitorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exhibitor::class);
    }
}