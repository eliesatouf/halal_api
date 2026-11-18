<?php
// src/State/HomepageDeferredProvider.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\HomepageDeferredData;
use App\Repository\FeaturedItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class HomepageDeferredProvider implements ProviderInterface
{
    public function __construct(
        private FeaturedItemRepository $featuredItemRepository,
        private EntityManagerInterface $entityManager,
        private CacheInterface $homepageCache
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$this->variantRepository) {
            throw new \Exception('VariantRepository is not injected!');
        }
        return $this->homepageCache->get('homepage_deferred', function(ItemInterface $item) {
            $item->expiresAfter(3600); // 1 hour - longer cache for heavy data
            
            $data = new HomepageDeferredData();
            
            // HEAVY data - can take time
            $data->featuredItems = $this->featuredItemRepository->findAll();
            
            return $data;
        });
    }
}