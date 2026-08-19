<?php
// src/State/HomepageCriticalProvider.php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\HomepageCriticalData;
use App\Repository\SettingsRepository;
use App\Repository\LandingpageRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class HomepageCriticalProvider implements ProviderInterface
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private LandingpageRepository $landingpageRepository,
        private CacheInterface $homepageCache,
        private LoggerInterface $logger,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->homepageCache->get('homepage_critical', function(ItemInterface $item) {
            $item->expiresAfter(0); 
            $this->settingsRepository->clearCache();
            $data = new HomepageCriticalData();
            
            // ONLY critical data - FAST queries
            $data->landingPage = $this->landingpageRepository->findOneBy(['id' => 1]);
            $data->workingHours = $this->settingsRepository->getValue('working_hours');
            $data->closingDate = $this->settingsRepository->getValue('closing_date');
            $data->closingMessage = $this->settingsRepository->getValue('closing_message');
            $data->reopeningMessage = $this->settingsRepository->getValue('re_opening_message');
            $data->firstOrderPercentage = $this->settingsRepository->getValue('first_order_discount_percentage');
            $strVal = $this->settingsRepository->getValue('first_order_enabled');

            $this->logger->error("############ $strVal");
            $this->logger->error("$$$$$$$$$$$ $data->closingDate");

            if($strVal == 1 || $strVal =='1' || $strVal == true )
            {
                $data->firstOrderPromoEnabled = 1;

                $this->logger->error("##SETING  true");
            }
            else
            {
                $data->firstOrderPromoEnabled = 0;
                 $this->logger->error("##SETING  false");
            }
            
            return $data;
        });
    }
}