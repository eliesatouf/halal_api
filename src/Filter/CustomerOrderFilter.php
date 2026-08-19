<?php
// src/Filter/CustomerOrderFilter.php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final class CustomerOrderFilter extends AbstractFilter
{
    public function __construct(private Security $security)
    {
        parent::__construct();
    }

    protected function filterProperty(
        string $property, 
        $value, 
        QueryBuilder $queryBuilder, 
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass, 
        Operation $operation = null, 
        array $context = []
    ): void {
        // Only apply to Order::class and GET collection
        if ($resourceClass !== \App\Entity\Order::class || !$operation instanceof GetCollection) {
            return;
        }

        // Force-filter by the current user's customer
        $user = $this->security->getUser();
        if ($user && method_exists($user, 'getCustomer')) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->andWhere("$rootAlias.customer = :current_customer")
                ->setParameter('current_customer', $user->getCustomer());
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return []; // No description needed (auto-applied filter)
    }
}