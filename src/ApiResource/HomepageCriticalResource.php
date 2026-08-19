<?php
// src/ApiResource/HomepageCriticalResource.php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Dto\HomepageCriticalData;
use App\State\HomepageCriticalProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/homepage-critical',
            output: HomepageCriticalData::class,
            provider: HomepageCriticalProvider::class,
            normalizationContext: ['groups' => ['homepage:read']],
            cacheHeaders: [
                'max_age' => 0,
                'shared_max_age' => 0,
                'public' => true
            ]
        )
    ]
)]
class HomepageCriticalResource
{
    // Marker class
}