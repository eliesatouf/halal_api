<?php
// src/Filter/menuNameFilter.php
namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;

class MenuNameFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        if ($property === 'parent') {
            $queryBuilder->andWhere("$rootAlias.parent = :parent")
                       ->setParameter('parent', filter_var("$value"));
        }
        if ($property === 'name') {
            $queryBuilder->andWhere("$rootAlias.name = :name")
                       ->setParameter('name', filter_var("$value"));
        }
        if ($property === 'type') {
            $queryBuilder->andWhere("$rootAlias.type = :type")
                       ->setParameter('type', filter_var("$value"));
        }
        if ($property === 'active') {
            $queryBuilder->andWhere("$rootAlias.active = :active")
                       ->setParameter('active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }

    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'active' => [
                'property' => 'parent',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by parent menu name'
            ]
        ];
    }
}