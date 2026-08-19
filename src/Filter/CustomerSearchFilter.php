<?php
// src/Filter/CustomerSearchFilter.php
namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;

class CustomerSearchFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        // Only apply filter if value is provided
        if (empty($value)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        
        if ($property == 'name') {
            // Use LIKE with wildcards for partial matching
            $queryBuilder->andWhere("LOWER($rootAlias.name) LIKE LOWER(:name)")
                       ->setParameter('name', '%' . $value . '%');
        }
        
        if ($property == 'telephone') {
            // For telephone, you might want partial match too
            $queryBuilder->andWhere("$rootAlias.telephone LIKE :telephone")
                       ->setParameter('telephone', '%' . $value . '%');
        }
        
        if ($property == 'email') {
            // For email, typically partial match on username part
            $queryBuilder->andWhere("LOWER($rootAlias.email) LIKE LOWER(:email)")
                       ->setParameter('email', '%' . $value . '%');
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'name' => [
                'property' => 'name',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter customer name (partial match)',
                'openapi' => [
                    'example' => 'john'
                ]
            ],
            'telephone' => [
                'property' => 'telephone',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter telephone number (partial match)',
                'openapi' => [
                    'example' => '555'
                ]
            ],
            'email' => [
                'property' => 'email',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter email address (partial match)',
                'openapi' => [
                    'example' => 'gmail'
                ]
            ]
        ];
    }
}