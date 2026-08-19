<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.orderDate BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('o.orderDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCompletedOrdersByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.orderDate BETWEEN :start AND :end')
            ->andWhere('o.orderStatus = :status')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('status', '5') //'5' is colosed status
            ->orderBy('o.orderDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getSalesSummaryByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('o')
            ->select([
                'COUNT(o.id) as total_orders',
                'SUM(o.grandTotal) as total_revenue',
                'SUM(o.totalDiscount) as total_discounts',
                'AVG(o.grandTotal) as avg_order_value'
            ])
            ->andWhere('o.orderDate BETWEEN :start AND :end')
            ->andWhere('o.orderStatus = :status')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('status', '5') // Completed orders only
            ->getQuery()
            ->getSingleResult();
    }


    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
