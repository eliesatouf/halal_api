<?php
// src/State/HomepageDataProvider.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\HomepageData;
use App\Repository\SettingsRepository;
use App\Repository\LandingpageRepository;  // Note: lowercase 'p'
use App\Repository\FeaturedItemRepository;
use Doctrine\ORM\EntityManagerInterface; // Add this for Category
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class HomepageDataProvider implements ProviderInterface
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private LandingpageRepository $landingpageRepository,  // Fixed: LandingpageRepository (not LandingPage)
        private FeaturedItemRepository $featuredItemRepository,
        private EntityManagerInterface $entityManager,  // For Category (since CategoryRepository doesn't exist)
        private CacheInterface $homepageCache
    ) {}

    // src/State/HomepageDataProvider.php

public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
{
    $isCli = php_sapi_name() === 'cli';
    
    if ($isCli) {
        // Skip cache for CLI warmup
        return $this->buildHomepageData();
    }
    
    return $this->homepageCache->get('homepage_data', function(ItemInterface $item) {
        $item->expiresAfter(3600);
        return $this->buildHomepageData();
    });
}

private function buildHomepageData(): HomepageData
{
    $data = new HomepageData();
    
    $data->landingPage = $this->landingpageRepository->findOneBy(['id' => 1]);
    $data->workingHours = $this->settingsRepository->getValue('working_hours');
    $data->featuredItems = $this->featuredItemRepository->findAll();
    
    $data->closingDate = $this->settingsRepository->getValue('closing_date');
    $data->closingMessage = $this->settingsRepository->getValue('closing_message');
    $data->reopeningMessage = $this->settingsRepository->getValue('re_opening_message');
    $data->firstOrderPromoEnabled = $this->settingsRepository->getValue('first_order_enabled');
    $data->firstOrderPromoEnabled = true;
    $data->firstOrderPercentage = $this->settingsRepository->getValue('first_order_discount.percentage');
    
    return $data;
}
}