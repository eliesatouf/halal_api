<?php
// src/ApiResource/HomepageDeferredResource.php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Dto\HomepageDeferredData;
use App\State\HomepageDeferredProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/homepage-deferred',
            output: HomepageDeferredData::class,
            provider: HomepageDeferredProvider::class,
            normalizationContext: ['groups' => ['homepage:read']],
            cacheHeaders: [
                'max_age' => 3600,
                'shared_max_age' => 3600,
                'public' => true
            ]
        )
    ]
)]
class HomepageDeferredResource
{
    // Marker class
}