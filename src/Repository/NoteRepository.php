<?php
// src/Repository/NoteRepository.php

namespace App\Repository;

use App\Entity\Note;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    /** @return Note[] */
    public function findForEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.entityType = :t')
            ->andWhere('n.entityId = :id')
            ->setParameter('t', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('n.createdOn', 'ASC')
            ->getQuery()
            ->getResult();
    }
}