<?php
// src/Controller/CacheTestController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;

class CacheTestController extends AbstractController
{
    #[Route('/cache-test', name: 'cache_test')]
    public function test(CacheInterface $settingsCache): Response
    {
        $cachedValue = $settingsCache->get('test_key', function() {
            return 'Cached at ' . date('Y-m-d H:i:s');
        });
        
        return new Response($cachedValue);
    }


    #[Route('/cache-test-1', name: 'cache_test_1')]
    public function test_1(CacheInterface $settingsCache): Response
    {

    if (method_exists($settingsCache, 'getPool')) {
        $pool = $settingsCache->getPool();
        $adapterClass = get_class($pool);
    } else {
        $adapterClass = get_class($settingsCache);
    }
    
    $cachedValue = $settingsCache->get('test_key', function() {
        return 'Cached at ' . date('Y-m-d H:i:s');
    });
    
    $info = "Adapter: " . $adapterClass . "\n\n";
    $info .= $cachedValue;
    
    return new Response("<pre>$info</pre>");
    }
}