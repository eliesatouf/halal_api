<?php
// src/Filter/menuNameFilter.php
namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;

class DishTypeFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        
        if ($property === 'type') {
            $queryBuilder->andWhere("$rootAlias.type = :type")
                       ->setParameter('type', filter_var("$value"));
        }

        if ($property === 'menu') {
            $queryBuilder->andWhere("$rootAlias.menu = :menu")
                       ->setParameter('menu', filter_var("$value"));
        }
        if ($property === 'active') {
            $queryBuilder->andWhere("$rootAlias.active = :active")
                       ->setParameter('active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'type' => [
                'property' => 'type',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by dish type (catering / dining)'
            ],
            'menu' => [
                'property' => 'menu',
                'menu' => 'string',
                'required' => false,
                'description' => 'Filter by dish menu '
            ],
            'active' => [
                'property' => 'active',
                'active' => 'boolean',
                'required' => false,
                'description' => 'Filter by active '
            ]
        ];
    }
}