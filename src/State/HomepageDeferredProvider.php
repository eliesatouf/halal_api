<?php
// src/State/HomepageDeferredProvider.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\HomepageDeferredData;
use App\Repository\FeaturedItemRepository;
use App\Repository\DishRepository;
use App\Repository\OfferRepository;
use App\Repository\ModifierRepository;
use App\Repository\ModifierOptionRepository;
use App\Repository\VariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class HomepageDeferredProvider implements ProviderInterface
{
    public function __construct(
        private FeaturedItemRepository $featuredItemRepository,
        private DishRepository $dishRepository,
        private OfferRepository $offerRepository,
        private ModifierRepository $modifierRepository,
        private ModifierOptionRepository $modifierOptionRepository,
        private VariantRepository $variantRepository,
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
            $data->dishes = $this->dishRepository->findBy(['active' => true]);
            $data->offers = $this->offerRepository->findBy([
                'active' => true,
                'assignedToHomePage' => true
            ]);
            $data->modifiers = $this->modifierRepository->findAll();
            $data->modifierOptions = $this->modifierOptionRepository->findAll();
            $data->variants = $this->variantRepository->findAll();

            
            // Categories
            $data->categories = $this->entityManager
                ->getRepository('App\Entity\Category')
                ->findAll();
            
            return $data;
        });
    }
}