<?php
namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Landingpage;
use App\Repository\LandingpageRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class LandingpageDataProvider implements ProviderInterface
{
    public function __construct(
        private LandingpageRepository $repository,
        private CacheInterface $settingsCache
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Handle single item by ID (usually ID=1)
        if (isset($uriVariables['id'])) {
            return $this->getCachedLandingpage($uriVariables['id']);
        }
        
        // Handle collection (though you probably only have one)
        return $this->getCachedLandingpage(1);
    }

    private function getCachedLandingpage(int $id): ?Landingpage
    {
        $cacheKey = 'landingpage_' . $id;
        
        return $this->settingsCache->get($cacheKey, function(ItemInterface $item) use ($id) {
            $item->expiresAfter(360); // 1 hour cache
            
            $landingpage = $this->repository->find($id);
            
            // If not found, cache a null placeholder for 5 minutes to prevent repeated DB hits
            if (!$landingpage) {
                $item->expiresAfter(300);
                return null;
            }
            
            return $landingpage;
        });
    }
}