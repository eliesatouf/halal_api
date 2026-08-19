<?php

namespace App\Repository;

use App\Entity\Dish;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dish>
 */
class DishRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dish::class);
    }

    public function findActiveDishesForMenu(): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.id', 'd.type', 'd.menu', 'd.name', 'd.description', 
                    'd.price', 'd.discountedPrice', 'd.imageSource', 
                    'd.status', 'd.active', 'd.enableAddToCart', 'd.prepTime',
                    'd.loyverseModifiersIds',
                    'c.id as categoryId', 'c.name as categoryName', 'c.slug as categorySlug',
                    'v.id as variantId', 'v.size', 'v.price as variantPrice')
            ->leftJoin('d.category', 'c')
            ->leftJoin('d.variants', 'v')
            ->where('d.active = :active')
            ->setParameter('active', true)
            ->orderBy('d.id', 'ASC');
        
        $results = $qb->getQuery()->getResult();
        
        // Group variants by dish
        $dishes = [];
        foreach ($results as $row) {
            $dishId = $row['id'];
            
            if (!isset($dishes[$dishId])) {
                $dishes[$dishId] = [
                    'id' => $row['id'],
                    'type' => $row['type'],
                    'menu' => $row['menu'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'price' => $row['price'],
                    'discountedPrice' => $row['discountedPrice'],
                    'imageSource' => $row['imageSource'],
                    'status' => $row['status'],
                    'active' => $row['active'],
                    'enableAddToCart' => $row['enableAddToCart'],
                    'prepTime' => $row['prepTime'],
                    'loyverseModifiersIds' => $row['loyverseModifiersIds'],
                    'category' => [
                        'id' => $row['categoryId'],
                        'name' => $row['categoryName'],
                        'slug' => $row['categorySlug']
                    ],
                    'variants' => [],
                    'promoStatus' => [
                        'active' => false,
                        'discount' => 0,
                        'description' => '',
                        'isBogo' => false
                    ]
                ];
            }
            
            // Add variant if exists
            if ($row['variantId']) {
                $dishes[$dishId]['variants'][] = [
                    'id' => $row['variantId'],
                    'size' => $row['size'],
                    'price' => $row['variantPrice']
                ];
            }
        }
        
        return array_values($dishes); // Reset keys
    }

    public function findByCategoryOrdered(int $categoryId): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.category', 'c')
            ->andWhere('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('d.sortId', 'ASC')
            ->addOrderBy('d.id', 'ASC') // Secondary ordering
            ->getQuery()
            ->getResult();
    }


    public function updateSortOrder(array $dishesWithSortIds): void
    {
        $qb = $this->createQueryBuilder('d');
        
        foreach ($dishesWithSortIds as $dishId => $sortId) {
            $qb->update(Dish::class, 'd')
                ->set('d.sortId', ':sortId')
                ->where('d.id = :dishId')
                ->setParameter('sortId', $sortId)
                ->setParameter('dishId', $dishId)
                ->getQuery()
                ->execute();
        }
    }
    
    //    /**
    //     * @return Dish[] Returns an array of Dish objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Dish
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
