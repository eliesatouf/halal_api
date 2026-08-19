<?php
// src/Filter/ActiveOfferFilter.php
namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;
use DateTime;

class ActiveOfferFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        if ($property === 'active') {
            $queryBuilder->andWhere("$rootAlias.active = :active")
                       ->setParameter('active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }
        if ($property === 'type') {
            $queryBuilder->andWhere("$rootAlias.type = :type")
                       ->setParameter('type', filter_var($value));
        }
        if ($property === 'assignedToHomePage') {
            $queryBuilder->andWhere("$rootAlias.assignedToHomePage = :assignedToHomePage")
                       ->setParameter('assignedToHomePage', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }
        if ($property === 'validDate') {
            $today = new DateTime();
            $queryBuilder->andWhere("$rootAlias.recordDate > :today")
                       ->setParameter('today', $today->format('Y-m-d'));
        }
        if ($property === 'currentlyValid') {
            $today = new DateTime();
            $queryBuilder
                ->andWhere("$rootAlias.startDate <= :today")
                ->andWhere("$rootAlias.endDate >= :today")
                ->setParameter('today', $today->format('Y-m-d'));
        }
    }

    public function getDescription(string $resourceClass): array
    {
         return [
            'active' => [
                'property' => 'active',
                'type' => 'bool',
                'required' => false,
                'description' => 'Filter by active status (true/false)'
            ],
            'assignedToHomePage' => [
                'property' => 'assignedToHomePage',
                'type' => 'bool',
                'required' => false,
                'description' => 'Filter by assigned to home page status (true/false)'
            ],
            'validDate' => [
                'property' => 'validDate',
                'type' => 'bool',
                'required' => false,
                'description' => 'Filter to only include records with date greater than today'
            ],
            'currentlyValid' => [
                'property' => 'currentlyValid',
                'type' => 'bool',
                'required' => false,
                'description' => 'Filter to only include records where today is between startDate and endDate'
            ]
        ];
    }
}