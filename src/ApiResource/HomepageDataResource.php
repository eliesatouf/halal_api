<?php
// src/ApiResource/HomepageDataResource.php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Dto\HomepageData;
use App\State\HomepageDataProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/homepage-data',
            output: HomepageData::class,
            provider: HomepageDataProvider::class,
            normalizationContext: ['groups' => ['homepage:read']],
            cacheHeaders: [
                'max_age' => 300,
                'shared_max_age' => 300,
                'public' => true
            ]
        )
    ]
)]
class HomepageDataResource
{
    // This class is just a marker for API Platform
}