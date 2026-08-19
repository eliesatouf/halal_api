<?php
// src/Filter/ActiveOfferFilter.php
namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;

class ActiveEventFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($property === 'active') {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->andWhere("$rootAlias.active = :active")
                       ->setParameter('active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }
        // if ($property === 'assignedToHomePage') {
        //     $rootAlias = $queryBuilder->getRootAliases()[0];
        //     $queryBuilder->andWhere("$rootAlias.assignedToHomePage = :assignedToHomePage")
        //                ->setParameter('assignedToHomePage', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        // }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'active' => [
                'property' => 'active',
                'type' => 'bool',
                'required' => false,
                'description' => 'Filter by active status (true/false)'
            ]
        ];
    }
}