<?php
// src/State/DishReorderProcessor.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\DishReorderInput;
use App\Repository\DishRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Psr\Log\LoggerInterface;

class DishReorderProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DishRepository $dishRepository,
        private LoggerInterface $logger
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        if (!$data instanceof DishReorderInput) {
            throw new BadRequestHttpException('Invalid data');
        }

        $items = $data->items;
        
        // Get all dish IDs
        $dishIds = array_column($items, 'id');
        
        if (empty($dishIds)) {
            throw new BadRequestHttpException('No dishes provided');
        }

        // Fetch all dishes in one query
        $dishes = $this->dishRepository->findBy(['id' => $dishIds]);
        
        if (count($dishes) !== count($dishIds)) {
            $foundIds = array_map(fn($dish) => $dish->getId(), $dishes);
            $missingIds = array_diff($dishIds, $foundIds);
            throw new BadRequestHttpException('Some dishes not found: ' . implode(', ', $missingIds));
        }

        // Create map for quick access
        $dishMap = [];
        foreach ($dishes as $dish) {
            $dishMap[$dish->getId()] = $dish;
        }

        // Update sortIds
        foreach ($items as $item) {

            if (isset($dishMap[$item['id']])) {
                $dishMap[$item['id']]->setSortId($item['sortId']);
                $this->logger->info('setSortId' . $item['sortId']);
                $this->logger->info('ID' . $item['id']);
            }
        }

        // Single flush
        $this->entityManager->flush();

        return [
            'success' => true,
            'message' => 'Dishes reordered successfully',
            'count' => count($items)
        ];
    }
}

